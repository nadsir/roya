<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index,follow">
    <meta name="description" content="ROYA GALLERY | فروشگاه آنلاین لباس، کیف و اکسسوری زنانه با طراحی مد روز و ارسال سریع.">

    <title>ROYA GALLERY | فروشگاه لباس و اکسسوری زنانه</title>

    @if (! empty($productSchema))
        <script type="application/ld+json" data-seo="product">{!! json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div id="app"></div>
</body>
</html>
