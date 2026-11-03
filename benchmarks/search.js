/**
 * Search against a listing of the same catalogue, at the same instant.
 *
 * This scenario exists because of one line in `.env.example`:
 *
 *   SCOUT_DRIVER=collection
 *
 * Scout's collection driver has no index. Every search loads the searchable
 * catalogue into PHP and matches substrings there, which `LunarProductCatalog`
 * already says in a comment is "fine for a small catalog but not something to
 * leave running against a large one". That is a true sentence nobody can act
 * on - this puts a number on it.
 *
 * Half the iterations search, the other half open a collection listing, both
 * interleaved in the same run so they meet the same database, the same cache
 * and the same contention. The listing is the control: it pages in SQL, so
 * its cost barely moves with catalogue size. Whatever the gap between the two
 * `page:` tags is, it is the driver.
 *
 *   ./bin/k6 run benchmarks/search.js                      # demo catalogue
 *   ./bin/artisan bench:catalog --products=2000 --force
 *   ./bin/k6 run benchmarks/search.js                      # same script, again
 *
 * Read `http_req_duration` for `page:search` next to `page:collection`. On
 * the demo catalogue they are within noise of each other; the point of the
 * second run is how far apart they have moved.
 */
import { navigate } from './lib/storefront.js';
import { discover, knob, numericKnob, pick } from './lib/target.js';

const vus = numericKnob('BENCH_VUS', 1);
const duration = knob('BENCH_DURATION', '30s');

export const options = {
    scenarios: {
        search_against_listing: {
            // A closed loop, not an arrival rate. The question here is what
            // one request costs next to another one, so the two must take
            // turns on an otherwise idle server - offer a rate instead and,
            // once search is slow, the queue it builds up slows the control
            // down with it and the comparison measures contention rather
            // than the driver. Capacity is capacity.js's question.
            executor: 'constant-vus',
            vus,
            duration,
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{page:collection}': [`p(95)<${numericKnob('BENCH_P95_LISTING', 600)}`],
        // Deliberately generous and deliberately set: without a threshold, a
        // search that has quietly become ten times slower still exits 0.
        'http_req_duration{page:search}': [`p(95)<${numericKnob('BENCH_P95_SEARCH', 1000)}`],
    },
};

export function setup() {
    return discover();
}

/**
 * Terms chosen to exercise the three cases separately, since they cost
 * different things: a common word that matches most of the catalogue, a rare
 * one that matches a handful, and one that matches nothing at all - which is
 * the worst case for an unindexed driver, because it has to look at
 * everything before concluding there is nothing.
 */
const TERMS = ['shirt', 'tee', 'cotton', 'leather', 'mug', 'bag', 'organic', 'zzzznothing'];

let counter = 0;

export default function (target) {
    const n = __VU * 37 + counter++;

    if (n % 2 === 0) {
        navigate(pick(target.collections, n), 'collection', target.version);

        return;
    }

    navigate(`/search?q=${encodeURIComponent(pick(TERMS, n))}`, 'search', target.version);
}
