/**
 * What the benchmarks need to know about the target, asked of the target
 * rather than written down here.
 *
 * A benchmark that hardcodes slugs and ids rots the first time someone
 * reseeds, and worse, it rots silently: the run still "passes", it just
 * measures 404s. Everything below is discovered at the start of a run from
 * two public endpoints (`/sitemap.xml` and one product page), so the same
 * script measures the demo catalogue, a scaled one (`artisan bench:catalog`)
 * and a real store's staging copy without being edited.
 *
 * Discovery runs in `setup()`, once per run, outside the measured scenarios -
 * none of its requests land in the results.
 */
import http from 'k6/http';

/**
 * The host to measure. No default pointing at production, ever: a benchmark
 * is a flood, and the one thing it must never do is find a real store
 * because someone forgot an environment variable.
 */
export const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8099').replace(/\/+$/, '');

/**
 * Browser-shaped headers. Laravel and Inertia both branch on `Accept`, and a
 * request that doesn't look like a browser's gets a different response (a
 * JSON error page instead of a redirect, for one), which would measure a
 * code path no visitor ever reaches.
 */
export const DOCUMENT_HEADERS = {
    Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language': 'en-GB,en;q=0.9',
    'User-Agent': 'k6/benchmarks (+ecommerce-hexa-template)',
};

/** Reads a tuning knob, falling back to the value the suite ships with. */
export function knob(name, fallback) {
    const raw = __ENV[name];

    return raw === undefined || raw === '' ? fallback : raw;
}

/** Same, for the ones that have to be numbers. */
export function numericKnob(name, fallback) {
    const value = Number(knob(name, fallback));

    if (!Number.isFinite(value)) {
        throw new Error(`${name} must be a number, got ${JSON.stringify(__ENV[name])}`);
    }

    return value;
}

/**
 * The Inertia page object a server-rendered response carries, as JSON.
 *
 * Every storefront page is an Inertia page, so this doubles as the proof that
 * a 200 is a real page and not an error template that happened to render:
 * `http_req_failed` only knows about status codes, and a benchmark that
 * measures a stack trace being rendered quickly has measured nothing.
 */
export function inertiaPage(body) {
    const match = /<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/.exec(body);

    if (match === null) {
        return null;
    }

    try {
        return JSON.parse(match[1]);
    } catch {
        return null;
    }
}

/**
 * Turns an absolute URL from the sitemap into a path on the target.
 *
 * The sitemap is generated with `route()`, so it names whatever `APP_URL`
 * says - which is the public hostname, not the port a benchmark stack
 * publishes locally. Only the path is reusable.
 */
function pathOf(url) {
    const withoutScheme = url.replace(/^[a-z][a-z0-9+.-]*:\/\//i, '');
    const slash = withoutScheme.indexOf('/');

    return slash === -1 ? '/' : withoutScheme.slice(slash);
}

function sitemapPaths(body) {
    const paths = [];
    const locations = /<loc>([^<]+)<\/loc>/g;
    let match;

    while ((match = locations.exec(body)) !== null) {
        paths.push(pathOf(match[1]));
    }

    return paths;
}

function expect(response, what) {
    if (response.status !== 200) {
        throw new Error(`${what} answered ${response.status} on ${BASE_URL} - is the target up and migrated?`);
    }

    return response;
}

/**
 * @returns {{
 *   version: string,
 *   products: string[],
 *   collections: string[],
 *   content: string[],
 *   bundles: Array<{id: number, slug: string}>,
 *   variants: number[],
 *   countryId: number|null,
 *   coupon: string,
 * }}
 */
export function discover() {
    const home = expect(http.get(`${BASE_URL}/`, { headers: DOCUMENT_HEADERS }), 'the home page');
    const page = inertiaPage(home.body);

    if (page === null) {
        throw new Error(`the home page carried no Inertia payload - ${BASE_URL} does not look like this storefront`);
    }

    const paths = sitemapPaths(expect(http.get(`${BASE_URL}/sitemap.xml`), 'the sitemap').body);
    const products = paths.filter((path) => path.startsWith('/products/'));
    const collections = paths.filter((path) => path.startsWith('/collections/'));
    const content = paths.filter((path) => path.startsWith('/news/') || path.startsWith('/pages/'));

    if (products.length === 0 || collections.length === 0) {
        throw new Error(`the sitemap lists ${products.length} products in ${collections.length} collections - seed the target first`);
    }

    // The variant ids the cart scenarios buy. Taken from one product page
    // rather than guessed from a range: ids are not contiguous after a
    // reseed, and adding a line for a variant that no longer exists is a 422
    // this suite would otherwise report as a slow success.
    const sample = inertiaPage(expect(http.get(`${BASE_URL}${products[0]}`, { headers: DOCUMENT_HEADERS }), `the product page ${products[0]}`).body);
    const variants = (sample?.props?.product?.variants ?? []).filter((variant) => (variant.available_stock ?? 1) > 0).map((variant) => variant.id);

    if (variants.length === 0) {
        throw new Error(`${products[0]} has no variant in stock - the cart scenarios would measure a refusal`);
    }

    // Bundles are not in the sitemap (they are this application's own
    // purchasable, not a Lunar product), so they come off their index page.
    const bundleIndex = inertiaPage(expect(http.get(`${BASE_URL}/bundles`, { headers: DOCUMENT_HEADERS }), 'the bundles page').body);
    const bundles = (bundleIndex?.props?.bundles ?? []).map((bundle) => ({ id: bundle.id, slug: bundle.slug }));

    // Any country does: the demo shipping zone is "unrestricted". A real
    // store's zones are country-scoped, which is why this is read from the
    // checkout page's own list instead of being pinned to one id.
    const checkout = inertiaPage(expect(http.get(`${BASE_URL}/checkout`, { headers: DOCUMENT_HEADERS }), 'the checkout page').body);
    const countryId = checkout?.props?.checkout?.countries?.[0]?.id ?? null;

    return {
        version: page.version,
        products,
        collections,
        content,
        bundles,
        variants,
        countryId,
        coupon: knob('BENCH_COUPON', 'WELCOME10'),
    };
}

/** Deterministic per-iteration pick, so two runs browse the same pages. */
export function pick(list, seed) {
    return list[seed % list.length];
}
