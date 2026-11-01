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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&display=swap">

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
    {{-- ============================================================= --}}
    {{-- Luxury brand preloader: full-screen overlay, blocks scroll,   --}}
    {{-- tracks window load / Vue mount, then removes itself.          --}}
    {{-- Lives outside #app so Vue never replaces it.                  --}}
    {{-- ============================================================= --}}
    <div id="rg-preloader" aria-hidden="false" role="status" aria-label="Loading ROYA GALLERY">
        <div class="rg-preloader-inner">
            <div class="rg-monogram" aria-hidden="true">RG</div>
            <h1 class="rg-preloader-title">ROYA GALLERY</h1>
            <p class="rg-preloader-sub">Haute Couture &mdash; Women's Fashion</p>
            <div class="rg-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div class="rg-progress-bar"></div>
            </div>
        </div>
    </div>

    <style>
        @keyframes rg-breathe {
            0%, 100% { opacity: .55; transform: scale(1); }
            50%      { opacity: 1;   transform: scale(1.04); }
        }
        @keyframes rg-sheen {
            from { transform: translateX(-100%); }
            to   { transform: translateX(300%); }
        }
        @keyframes rg-progress {
            from { width: 0%; }
            to   { width: 82%; }
        }
        #rg-preloader {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fbf9f5;
            overflow: hidden;
            opacity: 1;
            visibility: visible;
            transition: opacity .7s cubic-bezier(.22, 1, .36, 1), visibility 0s linear .7s, transform .7s cubic-bezier(.22, 1, .36, 1);
        }
        #rg-preloader.rg-done { opacity: 0; visibility: hidden; transform: scale(1.02); pointer-events: none; }
        #rg-preloader.rg-done .rg-preloader-inner { transform: translateY(-6px); opacity: 0; }
        body.rg-loading { overflow: hidden !important; }
        .rg-preloader-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 0 24px;
            transition: transform .7s cubic-bezier(.22, 1, .36, 1), opacity .5s ease;
        }
        .rg-monogram {
            font-family: "Cormorant Garamond", "Playfair Display", Georgia, serif;
            font-size: clamp(44px, 8vw, 72px);
            line-height: 1;
            color: #c5a880;
            letter-spacing: .12em;
            animation: rg-breathe 3s ease-in-out infinite;
        }
        .rg-preloader-title {
            margin: 22px 0 8px;
            font-family: "Cormorant Garamond", "Playfair Display", Georgia, serif;
            font-size: clamp(15px, 2.4vw, 20px);
            font-weight: 500;
            color: #1f1c19;
            letter-spacing: .45em;
            text-indent: .45em;
            text-transform: uppercase;
        }
        .rg-preloader-sub {
            margin: 0 0 34px;
            font-family: Georgia, serif;
            font-size: 11px;
            color: #8d8577;
            letter-spacing: .28em;
            text-transform: uppercase;
        }
        .rg-progress-track {
            position: relative;
            width: min(260px, 62vw);
            height: 1px;
            background: rgba(197, 168, 128, .25);
            overflow: hidden;
        }
        .rg-progress-bar {
            position: absolute;
            inset-block: 0;
            left: 0;
            width: 0%;
            background: linear-gradient(90deg, #c5a880, #e8d5ae 50%, #c5a880);
            animation: rg-progress 6s cubic-bezier(.4, 0, .2, 1) infinite;
        }
        .rg-progress-bar::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .85), transparent);
            width: 40%;
            animation: rg-sheen 2.2s linear infinite;
        }
        @media (prefers-reduced-motion: reduce) {
            .rg-monogram, .rg-progress-bar, .rg-progress-bar::after { animation: none; }
            .rg-progress-bar { width: 100%; }
        }
    </style>

    <script>
        (function () {
            var overlay = document.getElementById('rg-preloader');
            if (!overlay) return;
            document.documentElement.style.overflowY = 'hidden';
            var dismissed = false;

            function dismiss() {
                if (dismissed) return;
                dismissed = true;
                overlay.classList.add('rg-done');
                document.documentElement.style.overflowY = '';
                window.setTimeout(function () {
                    if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
                }, 800);
            }

            // Hide as soon as the page (or any vibe check) signals readiness.
            if (document.readyState === 'complete') {
                window.setTimeout(dismiss, 300);
            } else {
                window.addEventListener('load', function () { window.setTimeout(dismiss, 300); });
            }
            // Hard timeout so a hung sub-resource never locks the page.
            window.setTimeout(dismiss, 7000);
        })();
    </script>

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
