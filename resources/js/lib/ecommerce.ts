import type { Cart, CartLine, Money, Order, OrderLine, Product, ProductVariant } from '@/types/storefront';
import { trackEcommerceEvent } from './analytics';

/**
 * The GA4 ecommerce funnel: view_item → add_to_cart → begin_checkout →
 * purchase, plus the steps in between.
 *
 * Page views alone say how many pages were looked at. These say what was
 * looked at, what was put in a basket, where people stopped, and what the
 * shop actually took — which is the reason anyone installs analytics on a
 * store.
 *
 * Everything here is a no-op when no tag is configured: the helpers push to
 * `window.dataLayer` and `window.gtag`, and neither exists until the server
 * renders a tag.
 */

/**
 * A `Money` as GA4 wants it: a decimal number, not minor units.
 *
 * Sending `minor_amount` straight through reports every order at a hundred
 * times what it was, and nothing in Analytics flags it — you find out when
 * someone compares the revenue report to the Stripe dashboard. The number of
 * decimals is carried by the amount rather than assumed, because it is two
 * for the euro, zero for the yen and three for the dinar.
 */
export function toAmount(money: Money): number {
    if (money.decimal_places <= 0) {
        return money.minor_amount;
    }

    return money.minor_amount / 10 ** money.decimal_places;
}

/**
 * One line of an ecommerce event.
 *
 * `item_id` is the purchasable's own reference — a variant's SKU, a bundle's
 * slug — and it is deliberately the *same* string at every stage. The product
 * page reads it off the variant, the cart and the order read it off their
 * line, and Lunar copies it from one to the other when the order is placed.
 * That identity is the whole point: identify a shirt by sku when it is viewed
 * and by name once it is in the basket, and GA4 reports a view and a purchase
 * of two unrelated products.
 *
 * `item_name` is sent alongside because reports are read by people, and
 * because GA4 needs one of the two when a purchasable has no reference at all.
 */
interface EcommerceItem {
    item_id?: string;
    item_name: string;
    item_variant?: string;
    price: number;
    quantity: number;
}

function itemFromVariant(product: Product, variant: ProductVariant, quantity: number): EcommerceItem {
    return {
        item_id: variant.sku || undefined,
        item_name: product.name,
        item_variant: variant.option_summary || undefined,
        price: toAmount(variant.price),
        quantity,
    };
}

function itemFromLine(line: CartLine | OrderLine): EcommerceItem {
    return {
        item_id: line.sku ?? undefined,
        item_name: line.name,
        // Order lines carry no options of their own; cart lines may.
        item_variant: 'options' in line && line.options ? line.options : undefined,
        price: toAmount(line.unit_price),
        quantity: line.quantity,
    };
}

export function trackViewItem(product: Product, variant: ProductVariant): void {
    trackEcommerceEvent('view_item', {
        currency: variant.price.currency_code,
        value: toAmount(variant.price),
        items: [itemFromVariant(product, variant, 1)],
    });
}

export function trackAddToCart(product: Product, variant: ProductVariant, quantity: number): void {
    // At a price break, three mugs do not cost three times one mug. Reporting
    // the list price times the quantity overstates the basket by the whole
    // discount, and the funnel then shows value evaporating between the cart
    // and the purchase for a reason that is not abandonment.
    const tier = variant.tiers.find((step) => step.quantity === quantity);
    const unitPrice = tier ? tier.unit_price : variant.price;

    trackEcommerceEvent('add_to_cart', {
        currency: unitPrice.currency_code,
        value: tier ? toAmount(tier.total) : toAmount(unitPrice) * quantity,
        items: [{ ...itemFromVariant(product, variant, quantity), price: toAmount(unitPrice) }],
    });
}

/**
 * A quantity changed on the cart page.
 *
 * GA4 has no "quantity changed" event: going from 3 to 1 is a removal of two,
 * and going the other way is an addition of two. Reporting the whole line
 * either way would double-count what is already in the basket.
 */
export function trackQuantityChange(line: CartLine, previousQuantity: number, nextQuantity: number): void {
    const delta = nextQuantity - previousQuantity;

    if (delta === 0) {
        return;
    }

    const items = [{ ...itemFromLine(line), quantity: Math.abs(delta) }];

    trackEcommerceEvent(delta > 0 ? 'add_to_cart' : 'remove_from_cart', {
        currency: line.unit_price.currency_code,
        value: toAmount(line.unit_price) * Math.abs(delta),
        items,
    });
}

export function trackRemoveFromCart(line: CartLine): void {
    trackEcommerceEvent('remove_from_cart', {
        currency: line.unit_price.currency_code,
        value: toAmount(line.line_total),
        items: [itemFromLine(line)],
    });
}

/**
 * The cart stages report the subtotal, not the total.
 *
 * Shipping is not chosen yet and tax follows the address, so a total at this
 * point is a guess. `purchase` below reports what was actually charged.
 */
function cartEvent(name: string, cart: Cart): void {
    trackEcommerceEvent(name, {
        currency: cart.sub_total.currency_code,
        value: toAmount(cart.sub_total),
        coupon: cart.coupon_code ?? undefined,
        items: cart.lines.map(itemFromLine),
    });
}

export function trackViewCart(cart: Cart): void {
    cartEvent('view_cart', cart);
}

export function trackBeginCheckout(cart: Cart): void {
    cartEvent('begin_checkout', cart);
}

export function trackAddShippingInfo(cart: Cart, shippingTier: string): void {
    trackEcommerceEvent('add_shipping_info', {
        currency: cart.sub_total.currency_code,
        value: toAmount(cart.sub_total),
        coupon: cart.coupon_code ?? undefined,
        shipping_tier: shippingTier,
        items: cart.lines.map(itemFromLine),
    });
}

export function trackAddPaymentInfo(cart: Cart): void {
    trackEcommerceEvent('add_payment_info', {
        currency: cart.sub_total.currency_code,
        value: toAmount(cart.sub_total),
        coupon: cart.coupon_code ?? undefined,
        // The only one this shop has. A shop that adds another should send
        // what the customer actually chose.
        payment_type: 'Stripe',
        items: cart.lines.map(itemFromLine),
    });
}

/**
 * The one event with money in it.
 *
 * `value` is what was charged, shipping and tax included, with both also sent
 * on their own — that is the shape Google's own reference uses, and it is
 * what makes the revenue report comparable to the payment processor.
 *
 * Whether this fires at all is decided by the server, not here: the
 * confirmation page re-renders from the session, so a refresh would report a
 * second order that never happened.
 */
export function trackPurchase(order: Order): void {
    trackEcommerceEvent('purchase', {
        transaction_id: order.reference,
        currency: order.total.currency_code,
        value: toAmount(order.total),
        tax: toAmount(order.tax_total),
        shipping: toAmount(order.shipping_total),
        items: order.lines.map(itemFromLine),
    });
}
