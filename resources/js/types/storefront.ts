// Exact mirror of the PHP `toArray()` methods in
// app/Domain/{Catalog,Cart,Content}/*.php and app/Domain/Shared/Money.php.
// If a use case changes its output shape,
// this is the only file that needs updating on the frontend.

export interface Money {
    minor_amount: number;
    currency_code: string;
    formatted: string;
}

export interface ProductSummary {
    id: number;
    name: string;
    slug: string;
    thumbnail_url: string | null;
    price_from: Money;
    default_variant_id: number;
}

export interface ProductVariant {
    id: number;
    sku: string;
    option_summary: string;
    price: Money;
    // null = unlimited/made-to-order (no stock policy configured).
    available_stock: number | null;
}

export interface Product {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    thumbnail_url: string | null;
    images: string[];
    variants: ProductVariant[];
}

export interface CollectionSummary {
    id: number;
    name: string;
    slug: string;
}

export interface HeroSlide {
    id: number;
    title: string;
    subtitle: string | null;
    image_url: string;
    link_url: string | null;
}

export interface ProductListing {
    items: ProductSummary[];
    total: number;
    page: number;
    per_page: number;
    last_page: number;
}

export interface ListingFilters {
    sort: string;
    // Minor units, like every amount crossing the wire.
    min_price: number | null;
    max_price: number | null;
    in_stock_only: boolean;
}

export interface SortOption {
    value: string;
    label: string;
}

export interface Review {
    id: number;
    rating: number;
    body: string;
    author_name: string;
    verified_purchase: boolean;
    published_at: string;
}

export interface RatingSummary {
    average: number;
    count: number;
    // How many reviews gave each rating, keyed 1 to 5.
    distribution: Record<number, number>;
}

export interface ContentPageSummary {
    id: number;
    type: 'page' | 'post';
    title: string;
    slug: string;
    excerpt: string | null;
    image_url: string | null;
    // ISO 8601, formatted for display on the frontend.
    published_at: string | null;
}

export interface ContentPage extends ContentPageSummary {
    // Admin-authored HTML, rendered as markup like a product description.
    body: string;
}

export interface CartLine {
    id: number;
    product_variant_id: number;
    name: string;
    thumbnail_url: string | null;
    quantity: number;
    unit_price: Money;
    line_total: Money;
}

export interface Cart {
    id: number | null;
    lines: CartLine[];
    sub_total: Money;
    shipping_total: Money;
    discount_total: Money;
    tax_total: Money;
    total: Money;
    coupon_code: string | null;
}

export interface Address {
    country_id: number | null;
    first_name: string;
    last_name: string;
    company_name: string | null;
    line_one: string;
    line_two: string | null;
    city: string;
    state: string | null;
    postcode: string;
    contact_email: string | null;
    contact_phone: string | null;
}

export interface Country {
    id: number;
    name: string;
    iso2: string | null;
}

export interface ShippingOption {
    identifier: string;
    name: string;
    description: string | null;
    price: Money;
}

export interface CheckoutState {
    billing_address: Address | null;
    shipping_address: Address | null;
    selected_shipping_option: string | null;
}

export interface PaymentIntent {
    client_secret: string;
    publishable_key: string;
}

export interface CheckoutSummary {
    countries: Country[];
    shipping_options: ShippingOption[];
    state: CheckoutState;
    payment_intent: PaymentIntent | null;
}

export interface OrderLine {
    id: number;
    name: string;
    thumbnail_url: string | null;
    quantity: number;
    unit_price: Money;
    line_total: Money;
}

export interface OrderStatus {
    // Lunar's configured status handle - what logic keys on.
    handle: string;
    // What the customer reads.
    label: string;
}

export interface Order {
    status: OrderStatus;
    id: number;
    reference: string;
    placed: boolean;
    lines: OrderLine[];
    shipping_address: Address | null;
    billing_address: Address | null;
    sub_total: Money;
    shipping_total: Money;
    discount_total: Money;
    tax_total: Money;
    total: Money;
}

export interface OrderSummary {
    reference: string;
    placed_at: string;
    status: string;
    item_count: number;
    total: Money;
}
