/**
 * The read path under a fixed arrival rate: what a shopper waits for while
 * other people are browsing.
 *
 * Arrival rate, not "N virtual users looping as fast as they can". The
 * second measures how quickly a closed loop can be fed, which drifts with
 * every change to the application and tells you nothing you can put in front
 * of anyone. This one holds the request rate and lets latency move, which is
 * what a visitor experiences and what bends first when something regresses.
 *
 *   BASE_URL=http://localhost:8099 ./bin/k6 run benchmarks/browse.js
 *   BENCH_RATE=20 BENCH_DURATION=1m ./bin/k6 run benchmarks/browse.js
 *
 * One iteration is one request, so BENCH_RATE is requests per second. The
 * default of 8 is deliberately inside the flat part of the curve on the
 * machine this was written on - a threshold crossing there is a regression,
 * whereas a rate chosen near the knee crosses thresholds because the target
 * is saturated, which is `capacity.js`'s question and not this one.
 *
 * The mix below is weighted the way storefront traffic actually arrives -
 * mostly product and listing pages, in both shapes (see lib/storefront.js
 * for the document/navigation distinction) - rather than evenly, which would
 * let one rare, cheap page flatter the average.
 */
import http from 'k6/http';

import { document, navigate } from './lib/storefront.js';
import { BASE_URL, discover, knob, numericKnob, pick } from './lib/target.js';

const rate = numericKnob('BENCH_RATE', 8);
const duration = knob('BENCH_DURATION', '30s');

export const options = {
    scenarios: {
        browse: {
            executor: 'ramping-arrival-rate',
            startRate: Math.max(1, Math.round(rate / 5)),
            timeUnit: '1s',
            // Enough VUs that a slow response never becomes the reason a
            // request wasn't sent: with the rate held, a VU shortage shows up
            // as dropped iterations, which the thresholds refuse outright.
            preAllocatedVUs: Math.max(10, rate),
            maxVUs: Math.max(50, rate * 10),
            stages: [
                // Warm-up, short enough not to weigh on the percentiles:
                // opcache, the connection pool and Lunar's channel/currency
                // lookups all settle over the first few hundred requests.
                { target: Math.max(1, Math.round(rate / 5)), duration: '10s' },
                { target: rate, duration },
                { target: rate, duration },
            ],
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        // The honesty guard. The arrival-rate executor drops an iteration
        // when no VU is free to run it, which means the load generator - not
        // the application - decided the rate. Any dropped iteration
        // invalidates the run.
        dropped_iterations: ['count==0'],
        'http_req_duration{kind:document}': [`p(95)<${numericKnob('BENCH_P95_DOCUMENT', 500)}`],
        'http_req_duration{kind:navigation}': [`p(95)<${numericKnob('BENCH_P95_NAVIGATION', 400)}`],
    },
};

export function setup() {
    return discover();
}

/** One term that matches nothing, because a miss is a different query plan. */
const SEARCH_TERMS = ['shirt', 'tee', 'bag', 'mug', 'leather', 'cotton', 'zzzznothing'];

/**
 * Cumulative weights out of 100, so each entry reads as the percentage of
 * traffic it stands for.
 */
const MIX = [
    [20, (target, n) => document(pick(target.products, n), 'product')],
    [40, (target, n) => navigate(pick(target.products, n), 'product', target.version)],
    [50, (target, n) => document(pick(target.collections, n), 'collection')],
    [58, (target, n) => navigate(`${pick(target.collections, n)}?page=${(n % 3) + 1}`, 'collection.paged', target.version)],
    [65, (target, n) => navigate(`${pick(target.collections, n)}?sort=price_asc&in_stock=1`, 'collection.filtered', target.version)],
    [75, (target, n) => navigate(`/search?q=${pick(SEARCH_TERMS, n)}`, 'search', target.version)],
    [85, () => document('/', 'home')],
    [90, (target) => navigate('/', 'home', target.version)],
    [
        93,
        (target, n) =>
            target.bundles.length === 0 ? document('/bundles', 'bundles') : document(`/bundles/${pick(target.bundles, n).slug}`, 'bundle'),
    ],
    [97, (target, n) => (target.content.length === 0 ? document('/', 'home') : document(pick(target.content, n), 'content'))],
    [100, () => http.get(`${BASE_URL}/sitemap.xml`, { tags: { kind: 'document', page: 'sitemap' } })],
];

/**
 * Deterministic rather than random: two runs of the same script against the
 * same catalogue request the same pages in the same proportions, so a
 * difference between them is the application's and not the dice's. The VU
 * number is folded in so concurrent VUs aren't all on the same page at the
 * same instant.
 */
let counter = 0;

export default function (target) {
    const n = __VU * 37 + counter++;
    const slot = n % 100;

    for (const [ceiling, visit] of MIX) {
        if (slot < ceiling) {
            visit(target, n);

            return;
        }
    }
}
