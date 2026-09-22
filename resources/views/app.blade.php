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

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

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
