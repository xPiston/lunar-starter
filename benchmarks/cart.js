/**
 * The write path: what a cart mutation costs.
 *
 * Every request here goes through `throttle:storefront-write`, which is
 * **30 per minute per IP**. A load generator is one IP, so this scenario
 * cannot be turned up the way `browse.js` can - past the limit it stops
 * measuring the cart and starts measuring the limiter, which is
 * `rate-limit.js`'s job instead.
 *
 * That is why the rate here is per MINUTE, why it defaults to five carts a
 * minute (five writes each, so twenty-five of the thirty allowed), and why a
 * single 429 fails the run: `refused_writes` must be zero for the latencies
 * below to mean anything.
 *
 *   ./bin/k6 run benchmarks/cart.js
 *   BENCH_RATE=6 BENCH_DURATION=2m ./bin/k6 run benchmarks/cart.js
 *
 * To measure the cart under real load instead, raise the limiter on the
 * benchmark target (see benchmarks/README.md) rather than raising BENCH_RATE
 * and reading 429s as results.
 *
 * Each iteration is a whole cart from nothing: its own cookie jar, so its own
 * session and its own Lunar cart. Reusing one session would measure adding a
 * sixth line to a warm cart, which is not the request that matters.
 */
import { check } from 'k6';
import http from 'k6/http';

import { addLine, applyCoupon, cartLines, document, navigate, removeCoupon, removeLine, updateLine } from './lib/storefront.js';
import { discover, knob, numericKnob, pick } from './lib/target.js';

const rate = numericKnob('BENCH_RATE', 5);
const duration = knob('BENCH_DURATION', '1m');

export const options = {
    scenarios: {
        cart: {
            executor: 'constant-arrival-rate',
            rate,
            // Per minute, not per second. The limiter decides the unit.
            timeUnit: '1m',
            duration,
            preAllocatedVUs: 5,
            maxVUs: 20,
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        dropped_iterations: ['count==0'],
        // The run is void if the limiter spoke. Five writes per iteration
        // against 30/min leaves room for BENCH_RATE=6 and no more.
        refused_writes: ['count==0'],
        'http_req_duration{kind:write}': [`p(95)<${numericKnob('BENCH_P95_WRITE', 800)}`],
    },
};

export function setup() {
    return discover();
}

export default function (target) {
    // A jar of its own: a new visitor, with no session and no cart.
    const session = { jar: new http.CookieJar() };
    const n = __VU * 37 + __ITER;
    const product = pick(target.products, n);

    // The page the "add to cart" button is on. It is also what hands this
    // session its CSRF token - the write helpers read it from the jar.
    document(product, 'product', session);

    addLine(pick(target.variants, n), 1, product, session);

    const lines = cartLines(target.version, session);

    check(lines, { 'the cart holds the line that was added': (ids) => ids.length > 0 }, { page: 'cart' });

    if (lines.length === 0) {
        return;
    }

    // Three mutations on an existing cart: the quantity stepper, then the
    // coupon box both ways. Each one re-prices the whole cart through Lunar's
    // pipeline, which is the part worth timing.
    updateLine(lines[0], 2, session);
    applyCoupon(target.coupon, session);
    removeCoupon(session);

    navigate('/cart', 'cart.after', target.version, session);

    // Emptied again, which is the fifth and last write this iteration can
    // afford. A benchmark that leaves its carts behind makes the next run
    // slower than the last one and hands the abandoned-cart reminder a few
    // thousand addresses to think about.
    removeLine(lines[0], session);
}
