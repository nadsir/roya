<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @if (! empty($seo))
        {{-- Title, description, robots, canonical --}}
        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">

        {{-- OpenGraph / Twitter cards --}}
        @foreach ($seo['social'] as $property => $content)
            @if (Str::startsWith($property, 'og:'))
                <meta property="{{ $property }}" content="{{ $content }}">
            @else
                <meta name="{{ $property }}" content="{{ $content }}">
            @endif
        @endforeach

        {{-- JSON-LD structured data (Organization, Product, BreadcrumbList, FAQPage) --}}
        @foreach ($seo['jsonLd'] as $schema)
            @php
                $schemaType = $schema['@type'] ?? ($schema['@graph'][0]['@type'] ?? 'Unknown');
                if (is_array($schemaType)) {
                    $schemaType = reset($schemaType) ?: 'Unknown';
                }
            @endphp
            <script type="application/ld+json" data-seo="server" data-schema-type="{{ $schemaType }}">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endforeach
    @else
        <meta name="robots" content="index,follow">
        <meta name="description" content="ROYA GALLERY | فروشگاه آنلاین لباس، کیف و اکسسوری زنانه با طراحی مد روز و ارسال سریع.">
        <title>ROYA GALLERY | فروشگاه لباس و اکسسوری زنانه</title>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- Fallback styling for the server-rendered block. It only exists until
         Vue mounts #app and replaces its contents, but keeps the page readable
         for non-JS visitors and crawlers. --}}
    <style>
        #ssr-seo { max-width: 1180px; margin: 0 auto; padding: 28px 18px 48px; color: #1f2937; font-family: Tahoma, Vazirmatn, Arial, sans-serif; line-height: 1.9; }
        #ssr-seo h1 { margin: 0 0 14px; font-size: clamp(1.6rem, 4vw, 2.4rem); line-height: 1.4; }
        #ssr-seo h2 { margin: 28px 0 12px; font-size: 1.25rem; }
        #ssr-seo h3 { margin: 0 0 4px; font-size: 1rem; }
        #ssr-seo a { color: inherit; text-decoration: none; }
        #ssr-seo ul { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; margin: 0; padding: 0; list-style: none; }
        #ssr-seo li { border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px; background: #fff; }
        #ssr-seo img { display: block; width: 100%; height: auto; aspect-ratio: 4 / 5; object-fit: cover; border-radius: 8px; margin-bottom: 8px; }
        #ssr-seo table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        #ssr-seo th, #ssr-seo td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: right; }
        #ssr-seo details { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; margin: 8px 0; background: #fff; }
        #ssr-seo summary { cursor: pointer; font-weight: 700; }
        #ssr-seo details p { margin: 8px 0 0; }
    </style>
</head>

<body>
    {{-- The Vue app mounts here and replaces the server-rendered fallback. --}}
    <div id="app">
        @if (! empty($seoBlock))
            {{-- ============================================================= --}}
            {{-- Server-rendered SEO block: crawlable content for non-JS bots  --}}
            {{-- (search engines, AI answer engines). Vue replaces it on mount --}}
            {{-- so the running storefront never shows duplicate content.      --}}
            {{-- ============================================================= --}}
            <div id="ssr-seo">
                @if (isset($seoBlock['category']))
                    @include('seo.category-block', ['block' => $seoBlock])
                @elseif (isset($seoBlock['product']))
                    @include('seo.product-block', ['block' => $seoBlock])
                @endif
            </div>
        @endif
    </div>
</body>
</html>
