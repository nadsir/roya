<?php
/**
 * Server-rendered SEO block for product pages.
 *
 * Expected variables:
 * @var array{product: \App\Models\Product, h1: string, material: ?string,
 *     careInstructions: ?string, sizeChart: ?array{columns: array<int, string>,
 *     rows: array<int, array<int, string>>}, inStock: bool} $block
 */
$product = $block['product'];
$sizeChart = $block['sizeChart'];
$image = $product->images
    ->whereNull('variant_id')
    ->sortByDesc('is_primary')
    ->first();
$imagePath = $image?->path ?? '';
?>
<section class="ssr-seo-section">
    <h1>{{ $block['h1'] }}</h1>

    @if ($imagePath !== '')
        <img
            src="{{ Str::startsWith($imagePath, '/') || Str::startsWith($imagePath, 'http') ? $imagePath : '/storage/' . $imagePath }}"
            alt="{{ $image?->alt_text ?: $product->name . ' - گالری رویا' }}"
            loading="eager"
            decoding="async"
        >
    @endif

    @if ($product->short_description)
        <p>{{ $product->short_description }}</p>
    @endif

    @if ($product->description && $product->description !== $product->short_description)
        <h2>درباره {{ $product->name }}</h2>
        <p>{{ Str::limit(trim(strip_tags($product->description)), 900) }}</p>
    @endif

    @if ($product->sku)
        <p>کد کالا: {{ $product->sku }}</p>
    @endif

    @if ($product->price)
        <p>قیمت: {{ number_format((float) $product->price) }} تومان</p>
    @endif

    {{-- Machine-readable attribute table (GEO optimization) --}}
    <h2>مشخصات محصول</h2>
    <table>
        <caption>مشخصات {{ $product->name }}</caption>
        <tbody>
            @if ($product->brand)
                <tr><th scope="row">برند</th><td>{{ $product->brand }}</td></tr>
            @endif
            @if ($block['material'])
                <tr><th scope="row">جنس پارچه</th><td>{{ $block['material'] }}</td></tr>
            @endif
            <tr>
                <th scope="row">وضعیت موجودی</th>
                <td>{{ $block['inStock'] ? 'موجود' : 'ناموجود' }}</td>
            </tr>
            <tr><th scope="row">کشور تولید</th><td>ایران</td></tr>
        </tbody>
    </table>

    @if ($block['careInstructions'])
        <h2>راهنمای نگهداری</h2>
        <p>{{ $block['careInstructions'] }}</p>
    @endif

    @if ($sizeChart !== null)
        <h2>راهنمای سایز</h2>
        <table>
            <caption>جدول سایز {{ $product->name }}</caption>
            <thead>
                <tr>
                    @foreach ($sizeChart['columns'] as $column)
                        <th scope="col">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($sizeChart['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
