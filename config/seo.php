<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default description
    |--------------------------------------------------------------------------
    |
    | Used as the meta description and og:description on any page that doesn't
    | provide its own (the home page, legal pages...). Keep it under ~160
    | characters: search engines truncate beyond that.
    |
    */

    'description' => env(
        'SEO_DESCRIPTION',
        'An online store built on Laravel and Lunar: real products, real stock, secure checkout.',
    ),

    /*
    |--------------------------------------------------------------------------
    | Default share image
    |--------------------------------------------------------------------------
    |
    | Absolute URL used for og:image / twitter:image when a page has no image
    | of its own. Product pages override it with the product photo, which is
    | what actually gets shared. Leave null and link previews fall back to a
    | text-only card.
    |
    | Crawlers won't render SVG, so point this at a PNG or JPEG - ideally
    | 1200x630.
    |
    */

    'image' => env('SEO_IMAGE'),

];
