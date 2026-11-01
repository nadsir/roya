<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Central Technical SEO / GEO service for the storefront.
 *
 * Responsibilities:
 * - Canonical URL resolution (strips pagination/sort/filter query parameters,
 *   self-references paginated category pages).
 * - OpenGraph / Twitter meta tag payloads.
 * - JSON-LD structured data: Organization, WebSite, Product (with offers and
 *   merchant return policy), BreadcrumbList, and FAQPage.
 *
 * All URLs are absolute and built on the public production origin so
 * crawlers and AI engines see consistent entity URLs regardless of the
 * serving environment.
 */
class SeoService
{
    /** Public origin of the storefront (canonical host). */
    private const PRODUCTION_ORIGIN = 'https://royafashion.ir';

    /** Brand identity. */
    private const SITE_NAME = 'گالری رویا';
    private const SITE_NAME_EN = 'ROYA GALLERY';

    /** Default Twitter handle (without the leading @). */
    private const TWITTER_HANDLE = 'roya_gallery';

    /** Social profiles referenced from the Organization schema (sameAs). */
    private const SOCIAL_PROFILES = [
        'https://instagram.com/roya.gallery.fashion',
    ];

    /** ISO currency used in offers. */
    public const CURRENCY = 'IRR';

    /** Query parameters allowed to survive canonical stripping. */
    private const PAGINATION_PARAM = 'page';

    /** Query parameters that never appear on a canonical URL. */
    private const STRIPPED_PARAMS = ['sort', 'filter', 'q', 'view'];

    /** Merchant return policy terms (in days). */
    private const RETURN_WINDOW_DAYS = 7;

    public function __construct(
        private readonly ProductSchemaBuilder $productSchemas,
    ) {}

    // ------------------------------------------------------------------
    // URLs
    // ------------------------------------------------------------------

    /**
     * Absolute canonical origin: APP_URL in production-shaped environments,
     * the hardcoded storefront origin otherwise.
     */
    public function origin(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        $host = parse_url($appUrl, PHP_URL_HOST);
        if ($host === null || $host === 'localhost' || $host === '127.0.0.1') {
            return self::PRODUCTION_ORIGIN;
        }

        return $appUrl;
    }

    public function categoryUrl(Category $category, ?int $page = null): string
    {
        $url = $this->origin() . '/category/' . rawurlencode($category->slug);

        if ($page !== null && $page > 1) {
            // Self-referencing canonical for paginated listing pages keeps
            // each page independently indexable.
            $url .= '?page=' . $page;
        }

        return $url;
    }

    public function productUrl(Product $product): string
    {
        return $this->origin() . '/product/' . rawurlencode($product->slug);
    }

    /**
     * Canonical URL for a category page request, explicitly overriding the
     * stored canonical_url when pagination is present.
     */
    public function categoryCanonical(Category $category, ?int $page = null): string
    {
        if ($page !== null && $page > 1) {
            return $this->categoryUrl($category, $page);
        }

        return $this->explicitOr($category->canonical_url, $this->categoryUrl($category));
    }

    public function productCanonical(Product $product): string
    {
        return $this->explicitOr($product->canonical_url, $this->productUrl($product));
    }

    /**
     * Strips tracking/sort/filter parameters from the current request URL,
     * keeping only pagination (self-referencing canonicals for page > 1).
     */
    public function requestCanonical(string $path, array $query): string
    {
        $params = collect($query)
            ->except(self::STRIPPED_PARAMS)
            ->filter(fn ($value) => $value !== null && $value !== '');

        $url = $this->origin() . '/' . ltrim($path, '/');

        if ($params->isNotEmpty()) {
            $url .= '?' . http_build_query($params->all());
        }

        return $url;
    }

    // ------------------------------------------------------------------
    // OpenGraph / Twitter
    // ------------------------------------------------------------------

    /**
     * OpenGraph + Twitter meta tag payload for a page.
     *
     * @param array{title: string, description: string, url: string, type?: string, image?: ?string, site_name?: string} $page
     * @return array<string, string>
     */
    public function socialMeta(array $page): array
    {
        $tags = [
            'og:type' => $page['type'] ?? 'website',
            'og:title' => $page['title'],
            'og:description' => $page['description'],
            'og:url' => $page['url'],
            'og:site_name' => $page['site_name'] ?? self::SITE_NAME,
            'og:locale' => 'fa_IR',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $page['title'],
            'twitter:description' => $page['description'],
        ];

        if (! empty($page['image'])) {
            $tags['og:image'] = $page['image'];
            $tags['twitter:image'] = $page['image'];
        }

        return $tags;
    }

    // ------------------------------------------------------------------
    // JSON-LD schemas
    // ------------------------------------------------------------------

    /**
     * Organization + WebSite graph, emitted on every storefront page.
     *
     * @return array<string, mixed>
     */
    public function organizationSchema(): array
    {
        $origin = $this->origin();

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $origin . '/#organization',
                    'name' => self::SITE_NAME,
                    'alternateName' => self::SITE_NAME_EN,
                    'url' => $origin . '/',
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $origin . '/images/logo.png',
                    ],
                    'sameAs' => array_values(self::SOCIAL_PROFILES),
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $origin . '/#website',
                    'url' => $origin . '/',
                    'name' => self::SITE_NAME,
                    'inLanguage' => 'fa-IR',
                    'publisher' => ['@id' => $origin . '/#organization'],
                ],
            ],
        ];
    }

    /**
     * Product schema incl. offers, merchant return policy, and GEO-relevant
     * details (brand, fabric). Returns null when the product lacks the data
     * Google needs for a valid Product entity.
     *
     * @return array<string, mixed>|null
     */
    public function productSchema(Product $product): ?array
    {
        $schema = $this->productSchemas->build($product);

        if ($schema === null) {
            return null;
        }

        // Keep the offer URL, top-level URL, and @id aligned with the
        // slug-based canonical URL (including any explicit canonical override).
        $canonical = $this->productCanonical($product);
        $schema['url'] = $canonical;
        $schema['offers']['url'] = $canonical;
        $schema['offers']['hasMerchantReturnPolicy'] = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'IR',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => self::RETURN_WINDOW_DAYS,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/FreeReturn',
        ];

        $schema['offers']['shippingDetails'] = [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => '0',
                'currency' => self::CURRENCY,
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => 'IR',
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 0,
                    'maxValue' => 2,
                    'unitCode' => 'DAY',
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 7,
                    'unitCode' => 'DAY',
                ],
            ],
        ];

        $schema['brand'] = [
            '@type' => 'Brand',
            'name' => $product->brand ?: self::SITE_NAME,
        ];

        if ($product->material !== null && trim($product->material) !== '') {
            // Textile composition as machine-readable material nodes.
            $schema['material'] = $this->materialNodes($product->material);
        }

        if (isset($schema['@id']) === false) {
            $schema['@id'] = $canonical . '#product';
        }

        return $schema;
    }

    /**
     * BreadcrumbList schema reflecting the category hierarchy.
     *
     * @param array<int, array{name: string, url: string}> $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbSchema(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values($crumbs),
        ];
    }

    /**
     * Build the breadcrumb trail for a category (its full ancestor path).
     *
     * @param \Illuminate\Support\Collection<int, Category> $ancestors
     * @return array<int, array<string, mixed>>
     */
    public function categoryBreadcrumbs(Category $category, $ancestors): array
    {
        $items = [
            ['name' => 'خانه', 'url' => $this->origin() . '/'],
            ['name' => 'فروشگاه', 'url' => $this->origin() . '/store'],
        ];

        foreach ($ancestors as $ancestor) {
            $items[] = [
                'name' => $ancestor->name,
                'url' => $this->origin() . '/category/' . rawurlencode($ancestor->slug),
            ];
        }

        $items[] = [
            'name' => $category->name,
            'url' => $this->categoryUrl($category),
        ];

        return $this->breadcrumbSchema($this->toListItem($items));
    }

    /**
     * Build the breadcrumb trail for a product (deepest category first).
     *
     * @param \Illuminate\Support\Collection<int, Category> $categories
     * @return array<string, mixed>
     */
    public function productBreadcrumbs(Product $product, $categories): array
    {
        $items = [
            ['name' => 'خانه', 'url' => $this->origin() . '/'],
            ['name' => 'فروشگاه', 'url' => $this->origin() . '/store'],
        ];

        // Prefer the deepest assigned category so the trail names the most
        // specific collection the product belongs to.
        $category = $categories
            ->sortByDesc(function (Category $candidate) use ($categories) {
                $depth = 0;
                $parentId = $candidate->parent_id;
                $byId = $categories->keyBy('id');

                while ($parentId && $byId->has($parentId) && $depth < 20) {
                    $depth++;
                    $parentId = $byId->get($parentId)->parent_id;
                }

                return $depth;
            })
            ->first();

        if ($category) {
            // Include the category's ancestor path when those parents were
            // eager-loaded with the product.
            $byId = $categories->keyBy('id');
            $ancestors = collect();
            $parentId = $category->parent_id;
            while ($parentId && $byId->has($parentId) && $ancestors->count() < 20) {
                $parent = $byId->get($parentId);
                $ancestors->prepend($parent);
                $parentId = $parent->parent_id;
            }
            foreach ($ancestors as $ancestor) {
                $items[] = [
                    'name' => $ancestor->name,
                    'url' => $this->origin() . '/category/' . rawurlencode($ancestor->slug),
                ];
            }

            $items[] = [
                'name' => $category->name,
                'url' => $this->origin() . '/category/' . rawurlencode($category->slug),
            ];
        }

        $items[] = [
            'name' => $product->name,
            'url' => $this->productCanonical($product),
        ];

        return $this->breadcrumbSchema($this->toListItem($items));
    }

    /**
     * FAQPage schema for category / landing pages with FAQ data.
     *
     * @param array<int, array{question: string, answer: string}> $faqs
     * @return array<string, mixed>
     */
    public function faqSchema(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Split a Persian/English fabric list ("کرپ، ساتن") into schema material nodes.
     *
     * @return array<int, array<string, string>>
     */
    private function materialNodes(string $material): array
    {
        return collect(preg_split('/[،,\/]+/u', $material) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->map(fn (string $item) => [
                '@type' => 'Textile',
                'name' => $item,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array{name: string, url: string}> $items
     * @return array<int, array<string, mixed>>
     */
    private function toListItem(array $items): array
    {
        return collect($items)
            ->values()
            ->map(fn (array $item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])
            ->all();
    }

    private function explicitOr(?string $explicit, string $fallback): string
    {
        $explicit = trim((string) $explicit);

        return $explicit !== '' ? $explicit : $fallback;
    }
}
