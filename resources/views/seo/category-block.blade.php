<?php
/**
 * Server-rendered SEO block for category pages.
 *
 * Expected variables:
 * @var array{category: \App\Models\Category, h1: string, topDescription: ?string,
 *     bottomContent: ?string, faqs: array<int, array{question: string, answer: string}>,
 *     products: \Illuminate\Support\Collection<int, \App\Models\Product>, productCount: int} $block
 */
$products = $block['products'];
?>
<section class="ssr-seo-section">
    <h1>{{ $block['h1'] }}</h1>

    @if (! empty($block['topDescription']))
        <p>{{ $block['topDescription'] }}</p>
    @endif

    <p>تعداد {{ $block['productCount'] }} محصول در این مجموعه.</p>

    <h2>محصولات {{ $block['h1'] }}</h2>
    <ul>
        @forelse ($products as $product)
            @php
                $image = $product->images
                    ->whereNull('variant_id')
                    ->sortByDesc('is_primary')
                    ->first();
                $imagePath = $image?->path ?? '';
            @endphp
            <li>
                <a href="/product/{{ rawurlencode($product->slug) }}">
                    @if ($imagePath !== '')
                        <img
                            src="{{ Str::startsWith($imagePath, '/') || Str::startsWith($imagePath, 'http') ? $imagePath : '/storage/' . $imagePath }}"
                            alt="{{ $image?->alt_text ?: $product->name . ' - گالری رویا' }}"
                            loading="lazy"
                            decoding="async"
                        >
                    @endif
                    <h3>{{ $product->name }}</h3>
                </a>
                <span>موجود در گالری</span>
            </li>
        @empty
            <li>محصولی در این مجموعه ثبت نشده است.</li>
        @endforelse
    </ul>

    @if (! empty($block['bottomContent']))
        <h2>راهنمای استایل و انتخاب</h2>
        <div>{!! $block['bottomContent'] !!}</div>
    @endif

    @if (! empty($block['faqs']))
        <h2>سوالات پرتکرار</h2>
        @foreach ($block['faqs'] as $faq)
            <details class="ssr-seo-faq">
                <summary>{{ $faq['question'] }}</summary>
                <p>{{ $faq['answer'] }}</p>
            </details>
        @endforeach
    @endif
</section>
