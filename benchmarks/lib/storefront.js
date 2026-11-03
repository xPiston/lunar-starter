/**
 * The storefront as a load generator sees it: one function per thing a
 * shopper's browser actually does, each tagged so the summary can separate
 * them.
 *
 * Two request shapes, because the application answers in two:
 *
 *  - a **document** - the whole HTML page, which is what a first visit, a
 *    shared link and a crawler get;
 *  - a **navigation** - the Inertia JSON for the next page, which is what
 *    every click after that first visit gets.
 *
 * They are the same controller and the same queries with a twentieth of the
 * bytes, so mixing them into one average hides both numbers. Everything here
 * tags `kind` and `page`, and the scenarios threshold on those tags.
 *
 * What this suite deliberately does not do is fetch images, CSS or JS. k6 is
 * not a browser and shouldn't pretend to be one: those are static files
 * served without ever reaching PHP (by FrankenPHP here, by a CDN in front of
 * it in production), so loading them would move the numbers around without
 * telling you anything about the application.
 */
import { check, fail } from 'k6';
import http from 'k6/http';
import { Counter, Trend } from 'k6/metrics';

import { BASE_URL, DOCUMENT_HEADERS, inertiaPage } from './target.js';

/** Bytes served per page kind - the document/navigation gap, measured. */
export const pageBytes = new Trend('page_bytes');

/** Requests the write limiter refused (429). */
export const refusedWrites = new Counter('refused_writes');

/**
 * Navigations rejected because the asset version moved mid-run (409). It
 * means someone rebuilt the frontend while the benchmark was running, which
 * invalidates the run rather than failing the application.
 */
export const staleVersion = new Counter('stale_asset_version');

function record(response, kind, page) {
    pageBytes.add(response.body === null ? 0 : response.body.length, { kind, page });
}

/**
 * A full HTML page, as a cold visitor gets it.
 *
 * Checks the Inertia payload is there, not just that the status was 200: an
 * exception page also renders in 200 under some configurations, and "errors
 * render quickly" is not a benchmark result.
 */
export function document(path, page, params = {}) {
    const response = http.get(`${BASE_URL}${path}`, {
        ...params,
        headers: { ...DOCUMENT_HEADERS, ...(params.headers ?? {}) },
        tags: { kind: 'document', page, ...(params.tags ?? {}) },
    });

    record(response, 'document', page);

    check(
        response,
        {
            'document 200': (r) => r.status === 200,
            'document is an Inertia page': (r) => r.status === 200 && inertiaPage(r.body) !== null,
        },
        { page },
    );

    return response;
}

/**
 * The JSON for the next page, as a click gets it.
 *
 * `X-Inertia-Version` has to match the asset manifest hash or Inertia answers
 * 409 and tells the browser to do a full reload - so the version is
 * discovered once per run and handed down to every VU.
 */
export function navigate(path, page, version, params = {}) {
    const response = http.get(`${BASE_URL}${path}`, {
        ...params,
        headers: {
            ...DOCUMENT_HEADERS,
            'X-Inertia': 'true',
            'X-Inertia-Version': version,
            'X-Requested-With': 'XMLHttpRequest',
            ...(params.headers ?? {}),
        },
        tags: { kind: 'navigation', page, ...(params.tags ?? {}) },
    });

    record(response, 'navigation', page);

    if (response.status === 409) {
        staleVersion.add(1, { page });
    }

    check(
        response,
        {
            'navigation 200': (r) => r.status === 200,
            'navigation is JSON': (r) => r.status === 200 && (r.headers['Content-Type'] ?? '').includes('json'),
        },
        { page },
    );

    return response;
}

/**
 * The CSRF token, read where the browser reads it.
 *
 * Laravel sets `XSRF-TOKEN` on every response and accepts it back as the
 * `X-XSRF-TOKEN` header. The cookie value is URL-encoded (it ends in `%3D`),
 * and sending it as-is fails verification - which would show up as a uniform
 * 419 across the write scenarios.
 */
export function csrfToken(jar) {
    const cookies = (jar ?? http.cookieJar()).cookiesForURL(`${BASE_URL}/`);
    const raw = cookies['XSRF-TOKEN']?.[0];

    if (raw === undefined) {
        fail('no XSRF-TOKEN cookie: load a page before writing');
    }

    return decodeURIComponent(raw);
}

/**
 * A state-changing request, shaped the way the React frontend sends it.
 *
 * Three things here are load-bearing.
 *
 * `Referer`, because every one of these controllers answers with `back()`:
 * without it the redirect names `/`, and anything that follows the redirect
 * is then timing the home page instead of the page the shopper was on.
 *
 * `redirects: 0`, because that redirect is the *next* page, not this write.
 * Following it would fold a full page render into the cost of a cart
 * mutation, and the pages are already measured on their own - `journey.js`
 * makes the follow-up navigation explicitly, which is where it belongs.
 * (Following it also needs `X-Inertia-Version` on the way back, or Inertia
 * answers 409 and asks the browser to reload: the version is a GET-only
 * check, so a write that is not followed never meets it.)
 *
 * 429 is counted rather than failed. It is the write limiter (30/min per IP)
 * doing its job, and whether that is a result or a mistake depends on the
 * scenario: `rate-limit.js` is asking for it, the others are flagged for
 * running too hard from a single address.
 */
export function write(method, path, body, page, referer, params = {}) {
    const response = http.request(method, `${BASE_URL}${path}`, body === null ? null : JSON.stringify(body), {
        ...params,
        redirects: 0,
        headers: {
            ...DOCUMENT_HEADERS,
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(params.jar),
            'X-Inertia': 'true',
            'X-Requested-With': 'XMLHttpRequest',
            Referer: `${BASE_URL}${referer}`,
            ...(params.headers ?? {}),
        },
        tags: { kind: 'write', page, ...(params.tags ?? {}) },
    });

    if (response.status === 429) {
        refusedWrites.add(1, { page });
    }

    return response;
}

function checkedWrite(method, path, body, page, referer, params) {
    const response = write(method, path, body, page, referer, params);

    check(
        response,
        {
            // A redirect is the success case: `back()` answers 302, and
            // Inertia turns it into a 303 for anything that isn't a POST.
            'write accepted': (r) => r.status === 302 || r.status === 303 || (r.status >= 200 && r.status < 300),
        },
        { page },
    );

    return response;
}

export function addLine(variantId, quantity, productPath, params = {}) {
    return checkedWrite('POST', '/cart/lines', { product_variant_id: variantId, quantity }, 'cart.lines.store', productPath, params);
}

export function addBundle(bundleId, bundlePath, params = {}) {
    return checkedWrite('POST', '/cart/bundles', { bundle_id: bundleId, quantity: 1 }, 'cart.bundles.store', bundlePath, params);
}

export function updateLine(lineId, quantity, params = {}) {
    return checkedWrite('PATCH', `/cart/lines/${lineId}`, { quantity }, 'cart.lines.update', '/cart', params);
}

export function removeLine(lineId, params = {}) {
    return checkedWrite('DELETE', `/cart/lines/${lineId}`, null, 'cart.lines.destroy', '/cart', params);
}

export function applyCoupon(code, params = {}) {
    return checkedWrite('POST', '/cart/coupon', { code }, 'cart.coupon.store', '/cart', params);
}

export function removeCoupon(params = {}) {
    return checkedWrite('DELETE', '/cart/coupon', null, 'cart.coupon.destroy', '/cart', params);
}

/** The address a benchmark shopper lives at. Any country the zones cover. */
export function benchAddress(countryId) {
    return {
        country_id: countryId,
        first_name: 'Bench',
        last_name: 'Shopper',
        line_one: '1 Load Street',
        city: 'Testville',
        postcode: '12345',
        contact_email: 'bench@example.com',
    };
}

export function setAddress(countryId, params = {}) {
    const address = benchAddress(countryId);

    return checkedWrite('POST', '/checkout/address', { billing: address, shipping: address }, 'checkout.address', '/checkout', params);
}

export function selectShipping(identifier, params = {}) {
    return checkedWrite('POST', '/checkout/shipping-option', { identifier }, 'checkout.shipping-option', '/checkout', params);
}

/** The cart's own page, and the line ids on it. */
export function cartLines(version, params = {}) {
    const response = navigate('/cart', 'cart', version, params);
    const lines = response.status === 200 ? (response.json('props.cart.lines') ?? []) : [];

    return lines.map((line) => line.id);
}

/** The checkout page's shipping options, which only exist once an address does. */
export function shippingOptions(version, params = {}) {
    const response = navigate('/checkout', 'checkout', version, params);
    const options = response.status === 200 ? (response.json('props.checkout.shipping_options') ?? []) : [];

    return options.map((option) => option.identifier);
}
