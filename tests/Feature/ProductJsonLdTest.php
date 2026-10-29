<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that canonical product pages embed schema.org Product JSON-LD in the
 * initial HTML response (independent of Vue hydration), and that unrelated
 * pages never receive Product markup.
 */
class ProductJsonLdTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_page_contains_valid_product_json_ld(): void
    {
        $product = Product::create([
            'name' => 'مانتو لینن رویا',
            'slug' => 'roya-linen-coat',
            'sku' => 'RY-COAT-1',
            'short_description' => 'مانتو لینن سبک برای فصل',
            'price' => 1850000,
            'stock' => 4,
            'is_active' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/coat-1.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();
        $schema = $this->schemaByType($html, 'Product');

        $this->assertNotNull($schema, 'product page must embed product JSON-LD');
        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('Product', $schema['@type']);
        $this->assertSame('مانتو لینن رویا', $schema['name']);
        $this->assertSame('RY-COAT-1', $schema['sku']);
        $this->assertSame('مانتو لینن سبک برای فصل', $schema['description']);
        $this->assertSame(['https://royafashion.ir/storage/products/coat-1.jpg'], $schema['image']);
        $this->assertSame('https://royafashion.ir/product/roya-linen-coat', $schema['url']);

        $offer = $schema['offers'];
        $this->assertSame('Offer', $offer['@type']);
        $this->assertSame('https://royafashion.ir/product/roya-linen-coat', $offer['url']);
        $this->assertSame('IRR', $offer['priceCurrency']);
        $this->assertSame('1850000', $offer['price']);
        $this->assertSame('https://schema.org/InStock', $offer['availability']);
        $this->assertSame('https://schema.org/NewCondition', $offer['itemCondition']);
    }

    public function test_public_product_page_exposes_organization_and_breadcrumb_schema(): void
    {
        $product = Product::create([
            'name' => 'Structured Coat',
            'slug' => 'structured-coat',
            'price' => 400,
            'stock' => 1,
            'is_active' => true,
        ]);

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();

        $organization = $this->schemaByType($html, 'Organization');
        $this->assertNotNull($organization);
        $this->assertSame('گالری رویا', $organization['name']);

        $breadcrumbs = $this->schemaByType($html, 'BreadcrumbList');
        $this->assertNotNull($breadcrumbs);
        $this->assertNotEmpty($breadcrumbs['itemListElement']);
    }

    public function test_json_ld_is_escaped_and_does_not_break_out_of_script(): void
    {
        $product = Product::create([
            'name' => '</script><script>alert(1)</script>',
            'slug' => 'xss-check',
            'price' => 100,
            'stock' => 1,
            'is_active' => true,
        ]);

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script', $html);
        $schema = $this->schemaByType($html, 'Product');
        $this->assertSame('</script><script>alert(1)</script>', $schema['name']);
    }

    public function test_variant_price_uses_cheapest_active_variant(): void
    {
        $product = Product::create([
            'name' => 'Variant Coat',
            'slug' => 'variant-coat',
            'price' => 500,
            'stock' => 0,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 900,
            'stock' => 2,
            'is_active' => true,
            'combination_key' => 'a',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 700,
            'stock' => 1,
            'is_active' => true,
            'combination_key' => 'b',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 100,
            'stock' => 5,
            'is_active' => false,
            'combination_key' => 'c',
        ]);

        $schema = $this->schemaByType(
            $this->get("/product/{$product->slug}")->assertOk()->getContent(),
            'Product'
        );

        $this->assertSame('700', $schema['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
    }

    public function test_out_of_stock_product_reports_out_of_stock(): void
    {
        $product = Product::create([
            'name' => 'Sold Out',
            'slug' => 'sold-out',
            'price' => 250,
            'stock' => 0,
            'is_active' => true,
        ]);

        $schema = $this->schemaByType(
            $this->get("/product/{$product->slug}")->assertOk()->getContent(),
            'Product'
        );

        $this->assertSame('https://schema.org/OutOfStock', $schema['offers']['availability']);
    }

    public function test_inactive_product_returns_404_and_no_schema(): void
    {
        $product = Product::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'price' => 50,
            'is_active' => false,
        ]);

        $response = $this->get("/product/{$product->slug}")->assertStatus(404);

        $this->assertNull($this->schemaByType($response->getContent(), 'Product'));
    }

    public function test_missing_product_returns_404(): void
    {
        $this->get('/product/missing-product')->assertStatus(404);
        $this->get('/products/99999')->assertStatus(404);
    }

    public function test_legacy_numeric_product_url_redirects_to_canonical_slug(): void
    {
        $product = Product::create([
            'name' => 'Legacy',
            'slug' => 'legacy-product',
            'price' => 100,
            'stock' => 1,
            'is_active' => true,
        ]);

        $this->get("/products/{$product->id}")
            ->assertStatus(301)
            ->assertRedirect("/product/{$product->slug}");
    }

    public function test_product_without_price_has_no_product_schema(): void
    {
        $product = Product::create([
            'name' => 'No Price',
            'slug' => 'no-price',
            'price' => 0,
            'stock' => 1,
            'is_active' => true,
        ]);

        $response = $this->get("/product/{$product->slug}")->assertOk();

        $this->assertNull($this->schemaByType($response->getContent(), 'Product'));
    }

    public function test_non_product_pages_have_no_product_schema(): void
    {
        foreach (['/', '/store', '/cart', '/articles', '/login'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'application/ld+json',
                $html,
                "non-product page {$path} must not embed JSON-LD"
            );
        }
    }

    public function test_only_one_product_json_ld_block_is_emitted(): void
    {
        $product = Product::create([
            'name' => 'Single',
            'slug' => 'single',
            'price' => 100,
            'stock' => 1,
            'is_active' => true,
        ]);

        $html = $this->get("/product/{$product->slug}")->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-schema-type="Product"'));
        $this->assertSame(1, substr_count($html, 'data-schema-type="BreadcrumbList"'));
    }

    /**
     * Extract a decoded JSON-LD object by schema.org type. Supports both
     * direct @type blocks and the Organization/WebSite @graph wrapper.
     *
     * @return array<string, mixed>|null
     */
    private function schemaByType(string $html, string $type): ?array
    {
        if (! preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $json) {
            $decoded = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);

            if (! is_array($decoded)) {
                continue;
            }

            if (($decoded['@type'] ?? null) === $type) {
                return $decoded;
            }

            foreach (($decoded['@graph'] ?? []) as $node) {
                if (is_array($node) && ($node['@type'] ?? null) === $type) {
                    return $node;
                }
            }
        }

        return null;
    }
}
