@php
    // Rendered here rather than through Inertia's <Head> because social
    // crawlers never execute JavaScript: whatever they are shown has to be in
    // the HTML as served. See App\Http\Seo\PageMeta.
    $meta = $page['props']['meta'] ?? [];
    $siteName = config('app.name', 'Laravel');
    $title = $meta['title'] ?? $siteName;
    $description = $meta['description'] ?? null;
    $image = $meta['image_url'] ?? null;
    $canonical = url()->current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $title }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        <link rel="canonical" href="{{ $canonical }}">
        @if ($meta['noindex'] ?? false)
            {{-- Carts, checkout and anything reachable only with an order reference. --}}
            <meta name="robots" content="noindex, nofollow">
        @endif

        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:url" content="{{ $canonical }}">
        @if ($description)
            <meta property="og:description" content="{{ $description }}">
        @endif
        @if ($image)
            <meta property="og:image" content="{{ $image }}">
            <meta property="og:image:alt" content="{{ $title }}">
        @endif

        @foreach ($meta['og_properties'] ?? [] as $property => $content)
            <meta property="{{ $property }}" content="{{ $content }}">
        @endforeach

        <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $title }}">
        @if ($description)
            <meta name="twitter:description" content="{{ $description }}">
        @endif
        @if ($image)
            <meta name="twitter:image" content="{{ $image }}">
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/logo.svg" type="image/svg+xml">

        {{-- Two preconnects on purpose: the stylesheet and the font files it
             points at are served from different hosts, and the second one is
             only discovered after the CSS has been parsed. crossorigin is
             required on the gstatic hint - fonts are fetched in CORS mode, and
             without it the browser opens a connection it cannot reuse. --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">

        @if (! empty($meta['json_ld']))
            {{-- Structured data: what turns a product page into a rich result
                 (price, availability) rather than a plain blue link. --}}
            <script type="application/ld+json">@json($meta['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
        @endif

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
