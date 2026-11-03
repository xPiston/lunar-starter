/**
 * One shopper, end to end: landing on the home page to a checkout ready to
 * pay.
 *
 * `browse.js` measures pages and `cart.js` measures mutations; neither
 * answers "how long does buying something take". This does, as the sum of
 * every request one purchase needs - which is the only number in this
 * directory a non-engineer has any use for.
 *
 * It stops at the shipping choice. The step after it, `POST /checkout/complete`,
 * needs a Stripe payment intent, and a load test must never be the thing that
 * calls a payment provider a few thousand times: the limit is deliberate, not
 * an omission. `tests/Feature/Storefront/CheckoutTest.php` covers that step
 * with Stripe faked.
 *
 *   ./bin/k6 run benchmarks/journey.js
 *
 * Three writes per journey, so the 30/min write limiter caps this at ten
 * journeys a minute from one address - the rate is per minute for the same
 * reason as in cart.js, and a single 429 voids the run.
 */
import { check, group } from 'k6';
import http from 'k6/http';
import { Trend } from 'k6/metrics';

import { addLine, cartLines, document, navigate, selectShipping, setAddress, shippingOptions } from './lib/storefront.js';
import { discover, knob, numericKnob, pick } from './lib/target.js';

const rate = numericKnob('BENCH_RATE', 8);
const duration = knob('BENCH_DURATION', '1m');

/**
 * Server time for a whole purchase: every request added up, with no think
 * time in between. It is a floor, not a user-facing duration - a real shopper
 * spends longer reading the product page than the server spends on the entire
 * funnel, which is rather the point.
 */
const journeyDuration = new Trend('journey_duration', true);

export const options = {
    scenarios: {
        journey: {
            executor: 'constant-arrival-rate',
            rate,
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
        refused_writes: ['count==0'],
        journey_duration: [`p(95)<${numericKnob('BENCH_P95_JOURNEY', 4000)}`],
    },
};

export function setup() {
    return discover();
}

export default function (target) {
    const session = { jar: new http.CookieJar() };
    const n = __VU * 37 + __ITER;
    let spent = 0;

    const timed = (response) => {
        spent += response.timings.duration;

        return response;
    };

    group('arrive', () => {
        // A document, not a navigation: nobody arrives at a storefront with
        // the application already loaded in their browser.
        timed(document('/', 'home', session));
    });

    const collection = pick(target.collections, n);
    const product = pick(target.products, n);

    group('browse', () => {
        timed(navigate(collection, 'collection', target.version, session));
        timed(navigate(`${collection}?sort=price_asc`, 'collection.sorted', target.version, session));
        timed(navigate(product, 'product', target.version, session));
    });

    group('add to cart', () => {
        timed(addLine(pick(target.variants, n), 1, product, session));
    });

    let lines = [];

    group('review the cart', () => {
        lines = cartLines(target.version, session);
    });

    check(lines, { 'the journey has something to buy': (ids) => ids.length > 0 }, { page: 'cart' });

    if (lines.length === 0) {
        return;
    }

    group('checkout', () => {
        timed(navigate('/checkout', 'checkout', target.version, session));

        if (target.countryId === null) {
            return;
        }

        timed(setAddress(target.countryId, session));

        // Shipping options only exist once an address does - they are priced
        // against the destination, so this request cannot be skipped or
        // reordered.
        const options = shippingOptions(target.version, session);

        check(options, { 'an address makes shipping options appear': (list) => list.length > 0 }, { page: 'checkout' });

        if (options.length > 0) {
            timed(selectShipping(options[0], session));
        }
    });

    journeyDuration.add(spent);
}
