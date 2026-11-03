/**
 * One shopper, one pass, every URL the other scenarios use.
 *
 * This is not a load test and its timings are not a result - it exists so
 * that a red benchmark can be told apart from a broken benchmark. Run it
 * first: if `smoke.js` passes, every path the heavier scenarios hit answers
 * correctly and any number they then produce is about speed. If it fails,
 * nothing else in this directory is worth running.
 *
 * It is also the only scenario safe to put in CI: single-threaded, a handful
 * of seconds, and it asserts behaviour (`checks` must be 100%) rather than
 * latency, which a shared CI runner cannot measure meaningfully anyway.
 *
 *   BASE_URL=http://localhost:8099 ./bin/k6 run benchmarks/smoke.js
 */
import { check, group } from 'k6';
import http from 'k6/http';

import {
    addBundle,
    addLine,
    applyCoupon,
    cartLines,
    document,
    navigate,
    removeCoupon,
    removeLine,
    selectShipping,
    setAddress,
    shippingOptions,
    updateLine,
} from './lib/storefront.js';
import { BASE_URL, discover, pick } from './lib/target.js';

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        // A smoke run makes no allowance for failure: one bad response and
        // the exit code is non-zero.
        checks: ['rate==1.00'],
        http_req_failed: ['rate==0.00'],
    },
};

export function setup() {
    return discover();
}

export default function (target) {
    group('catalogue', () => {
        document('/', 'home');
        navigate('/', 'home', target.version);

        for (const collection of target.collections) {
            document(collection, 'collection');
            navigate(`${collection}?sort=price_asc&in_stock=1`, 'collection.filtered', target.version);
        }

        // Capped: a smoke run proves the shape of a page, not the size of the
        // catalogue, and `bench:catalog` can leave thousands behind.
        for (const product of target.products.slice(0, 3)) {
            document(product, 'product');
            navigate(product, 'product', target.version);
        }

        for (const page of target.content.slice(0, 3)) {
            document(page, 'content');
        }
    });

    group('search', () => {
        navigate('/search?q=shirt', 'search', target.version);
        // The empty term is a distinct path: it renders an empty listing
        // without touching the search engine at all.
        navigate('/search', 'search.empty', target.version);
    });

    group('bundles', () => {
        document('/bundles', 'bundles');

        for (const bundle of target.bundles.slice(0, 2)) {
            document(`/bundles/${bundle.slug}`, 'bundle');
        }
    });

    group('crawler', () => {
        const sitemap = http.get(`${BASE_URL}/sitemap.xml`, { tags: { kind: 'document', page: 'sitemap' } });
        const robots = http.get(`${BASE_URL}/robots.txt`, { tags: { kind: 'document', page: 'robots' } });

        check(sitemap, { 'sitemap is XML': (r) => r.status === 200 && r.body.includes('<urlset') });
        check(robots, { 'robots names the sitemap': (r) => r.status === 200 && r.body.includes('sitemap.xml') });
    });

    group('cart', () => {
        const product = pick(target.products, 0);

        addLine(target.variants[0], 1, product);

        if (target.bundles.length > 0) {
            addBundle(target.bundles[0].id, `/bundles/${target.bundles[0].slug}`);
        }

        const lines = cartLines(target.version);

        check(lines, { 'the cart holds what was added': (ids) => ids.length > 0 });

        if (lines.length > 0) {
            updateLine(lines[0], 2);
        }

        applyCoupon(target.coupon);
        removeCoupon();
    });

    group('checkout', () => {
        document('/checkout', 'checkout');

        if (target.countryId !== null) {
            setAddress(target.countryId);

            const options = shippingOptions(target.version);

            check(options, { 'an address makes shipping options appear': (list) => list.length > 0 });

            if (options.length > 0) {
                selectShipping(options[0]);
            }
        }
    });

    // Leaves the cart as it was found. Nothing downstream depends on it, but
    // a smoke run that quietly fills a database with abandoned carts earns
    // itself a reminder email per run.
    group('tidy up', () => {
        for (const line of cartLines(target.version)) {
            removeLine(line);
        }
    });
}
