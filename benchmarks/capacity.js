/**
 * How much can this target hold?
 *
 * The other scenarios hold the load steady and watch latency. This one walks
 * the arrival rate up in steps and lets you see where the curve bends - which
 * is the number you need before you can choose a sensible rate for anything
 * else, or decide whether classic FrankenPHP mode is enough (see the note in
 * the Dockerfile about worker mode).
 *
 *   BASE_URL=http://localhost:8099 ./bin/k6 run benchmarks/capacity.js
 *   BENCH_RATE=40 ./bin/k6 run benchmarks/capacity.js      # if 20 wasn't enough
 *
 * It steps 1/4, 1/2, 3/4 and 4/4 of BENCH_RATE, a BENCH_DURATION each.
 *
 * How to read it. There is no latency threshold here on purpose: being slow
 * at the top of the ramp is the finding, not a failure. Two things to look
 * for in the summary:
 *
 *  - `dropped_iterations` above zero means k6 ran out of VUs before the
 *    target ran out of capacity, so the top step never really happened;
 *  - `http_reqs` per second well below BENCH_RATE means the same thing from
 *    the other side - the target could not take what was offered.
 *
 * What must not happen is an error. A saturated server answers slowly; one
 * that answers 500 or drops the connection under load has a different
 * problem, and that is the one threshold below.
 */
import http from 'k6/http';

import { document, navigate } from './lib/storefront.js';
import { BASE_URL, discover, knob, numericKnob, pick } from './lib/target.js';

const rate = numericKnob('BENCH_RATE', 20);
const duration = knob('BENCH_DURATION', '20s');

const step = (fraction) => ({ target: Math.max(1, Math.round(rate * fraction)), duration });

export const options = {
    scenarios: {
        capacity: {
            executor: 'ramping-arrival-rate',
            startRate: Math.max(1, Math.round(rate / 4)),
            timeUnit: '1s',
            preAllocatedVUs: Math.max(20, rate * 2),
            // Generous: a VU shortage would cap the ramp before the target
            // does, which is the one way this scenario can lie.
            maxVUs: Math.max(200, rate * 20),
            stages: [step(0.25), step(0.5), step(0.75), step(1)],
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

export function setup() {
    return discover();
}

/** The same mix as browse.js, kept simple: this is about volume, not shape. */
const MIX = [
    (target, n) => document(pick(target.products, n), 'product'),
    (target, n) => navigate(pick(target.products, n), 'product', target.version),
    (target, n) => navigate(pick(target.collections, n), 'collection', target.version),
    (target, n) => document(pick(target.collections, n), 'collection'),
    () => document('/', 'home'),
    () => http.get(`${BASE_URL}/sitemap.xml`, { tags: { kind: 'document', page: 'sitemap' } }),
];

let counter = 0;

export default function (target) {
    const n = __VU * 37 + counter++;

    MIX[n % MIX.length](target, n);
}
