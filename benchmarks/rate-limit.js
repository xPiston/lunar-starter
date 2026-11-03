/**
 * The limiter under flood: does it hold, and what does holding cost?
 *
 * `tests/Feature/Storefront/RateLimitingTest.php` already proves 30 requests
 * get through and the 31st gets a 429. That is the behaviour. This asks the
 * other half of the question, which a functional test cannot: when a few
 * hundred write requests a minute arrive from one address, how much work does
 * each refusal still do?
 *
 * The answer is not "none", and it is worth knowing. `throttle:storefront-write`
 * is route middleware, so it runs AFTER the `web` group: by the time a request
 * is refused, Laravel has already booted, decrypted the session cookie, loaded
 * the session row from Postgres and verified the CSRF token. A 429 is cheaper
 * than a cart mutation, not free - so the gap between `refused_duration` and
 * `accepted_duration` below is what the application spends on traffic it has
 * decided to throw away.
 *
 *   ./bin/k6 run benchmarks/rate-limit.js
 *
 * Run it against a benchmark stack, never a shared one: it deliberately parks
 * the generator's IP at its write limit, and anything else browsing from that
 * address is refused too for the rest of the minute.
 */
import { check } from 'k6';
import http from 'k6/http';
import { Counter, Trend } from 'k6/metrics';

import { document, write } from './lib/storefront.js';
import { discover, knob, numericKnob, pick } from './lib/target.js';

const vus = numericKnob('BENCH_VUS', 10);
const duration = knob('BENCH_DURATION', '30s');

/** What a refused write costs, and what an accepted one costs, side by side. */
const refusedDuration = new Trend('refused_duration', true);
const acceptedDuration = new Trend('accepted_duration', true);
const accepted = new Counter('accepted_writes');

export const options = {
    scenarios: {
        flood: {
            executor: 'constant-vus',
            vus,
            duration,
        },
    },
    thresholds: {
        // 429 is the expected answer here, so the usual failure rate is
        // meaningless: what matters is that the limiter answered at all...
        refused_writes: ['count>0'],
        // ...and that it did not let the flood through. 30 per minute, and a
        // 30s burst can straddle at most two windows, so 60 is the ceiling
        // this can legitimately reach. Anything above it means the limiter
        // is not keyed the way it looks.
        accepted_writes: ['count<=60'],
        checks: ['rate>0.99'],
    },
};

export function setup() {
    return discover();
}

/**
 * One session per VU, established once and kept.
 *
 * Not `http.cookieJar()`: k6 hands each iteration a fresh default jar, so a
 * session opened on one iteration is gone by the next and every write after
 * the first would go out without a CSRF token. A jar of our own, held in
 * module scope (which does persist across a VU's iterations), is what makes
 * this a flood from a handful of sessions rather than hundreds of them.
 *
 * It matters for what is being measured, too: the limiter keys on IP, not on
 * session, so a new session per request would have every iteration paying for
 * a session INSERT that has nothing to do with the refusal being timed.
 */
let session = null;

function sessionFor(target) {
    if (session === null) {
        session = { jar: new http.CookieJar() };

        // Any page will do - it is here to be handed a session cookie and a
        // CSRF token, and it is deliberately outside the loop below.
        document(pick(target.products, __VU), 'product', session);
    }

    return session;
}

export default function (target) {
    const session = sessionFor(target);

    const response = write('POST', '/cart/lines', { product_variant_id: target.variants[0], quantity: 1 }, 'flood', '/cart', session);

    if (response.status === 429) {
        refusedDuration.add(response.timings.duration);
    } else {
        acceptedDuration.add(response.timings.duration);
        accepted.add(1);
    }

    check(
        response,
        {
            // Either the write worked or the limiter refused it. A 419
            // (expired CSRF), a 500 or anything else means this scenario is
            // measuring something it did not intend to.
            'answered either accepted or 429': (r) => r.status === 429 || (r.status >= 200 && r.status < 400),
        },
        { page: 'flood' },
    );
}
