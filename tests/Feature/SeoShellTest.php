<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the public slug-based SEO shell: canonical URLs, crawlable
 * server-rendered content, redirects, API identifier lookup, and sitemap.
 */
class SeoShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_page_serves_canonical_seo_shell(): void
    {
        $category = Category::create([
            'name' => 'مانتو',
            'slug' => 'manto',
            'description' => 'مجموعه مانتو',
            'meta_title' => 'خرید مانتو شیک',
            'meta_description' => 'خرید مانتو از گالری رویا',
            'h1_title' => 'مانتوهای رویا',
            'top_description' => 'مانتوهای طراحی‌شده برای هر روز.',
            'bottom_content' => 'راهنمای انتخاب مانتو.',
            'faqs' => [
                ['question' => 'سایزبندی چگونه است؟', 'answer' => 'طبق جدول سایز.'],
            ],
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'مانتو کتان',
            'slug' => 'cotton-manto',
            'short_description' => 'مانتو کتان سبک',
            'price' => 1200000,
            'stock' => 3,
            'is_active' => true,
        ]);
        $product->categories()->attach($category->id);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/cotton-manto.jpg',
            'alt_text' => 'مانتو کتان رویا',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $html = $this->get('/category/manto')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<link rel="canonical" href="https://royafashion.ir/category/manto">',
            $html
        );
        $this->assertStringContainsString('<h1>مانتوهای رویا</h1>', $html);
        $this->assertStringContainsString('data-schema-type="BreadcrumbList"', $html);
        $this->assertStringContainsString('data-schema-type="FAQPage"', $html);
        $this->assertStringContainsString('<details class="ssr-seo-faq">', $html);
        $this->assertStringContainsString('id="ssr-seo"', $html);
        $this->assertStringNotContainsString('id="ssr-seo" hidden', $html);
        $this->assertStringContainsString('/product/cotton-manto', $html);
        $this->assertStringContainsString('alt="مانتو کتان رویا"', $html);
    }

    public function test_legacy_category_url_redirects_permanently(): void
    {
        Category::create([
            'name' => 'مانتو',
            'slug' => 'manto',
            'is_active' => true,
        ]);
        Category::create([
            'name' => 'کلاه',
            'slug' => 'کلاه',
            'is_active' => true,
        ]);

        $this->get('/c/manto')
            ->assertStatus(301)
            ->assertRedirect('/category/manto');

        $this->get('/c/' . rawurlencode('کلاه'))
            ->assertStatus(301)
            ->assertRedirect('/category/' . rawurlencode('کلاه'));
    }

    public function test_product_page_contains_semantic_block_and_product_schema(): void
    {
        $product = Product::create([
            'name' => 'کیف چرم',
            'slug' => 'leather-bag',
            'short_description' => 'کیف چرم دست‌دوز',
            'description' => 'کیف چرم طبیعی با دوخت دست.',
            'price' => 2400000,
            'stock' => 2,
            'brand' => 'گالری رویا',
            'material' => 'چرم طبیعی',
            'care_instructions' => 'با پارچه نرم تمیز کنید.',
            'is_active' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/leather-bag.jpg',
            'alt_text' => 'کیف چرم دست‌دوز رویا',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $html = $this->get('/product/leather-bag')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<link rel="canonical" href="https://royafashion.ir/product/leather-bag">',
            $html
        );
        $this->assertStringContainsString('data-schema-type="Product"', $html);
        $this->assertStringContainsString(
            '<meta property="og:image" content="https://royafashion.ir/storage/products/leather-bag.jpg">',
            $html
        );
        $this->assertStringContainsString('<h1>کیف چرم</h1>', $html);
        $this->assertStringContainsString('چرم طبیعی', $html);
        $this->assertStringContainsString('با پارچه نرم تمیز کنید.', $html);
        $this->assertStringContainsString('alt="کیف چرم دست‌دوز رویا"', $html);
    }

    public function test_api_product_lookup_accepts_slug_and_numeric_id(): void
    {
        $product = Product::create([
            'name' => 'شال ابریشم',
            'slug' => 'silk-scarf',
            'price' => 800000,
            'stock' => 4,
            'is_active' => true,
        ]);

        $this->getJson('/api/products/silk-scarf')
            ->assertOk()
            ->assertJsonPath('name', 'شال ابریشم');

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('name', 'شال ابریشم');

        $this->getJson('/api/products/unknown-slug')->assertStatus(404);
    }

    public function test_sitemap_uses_only_canonical_url_structures(): void
    {
        $category = Category::create([
            'name' => 'کیف',
            'slug' => 'bags',
            'is_active' => true,
        ]);

        $inStock = Product::create([
            'name' => 'کیف چرم',
            'slug' => 'leather-bag',
            'price' => 100,
            'stock' => 1,
            'is_active' => true,
        ]);

        $outOfStock = Product::create([
            'name' => 'کیف فروخته‌شده',
            'slug' => 'sold-out-bag',
            'price' => 100,
            'stock' => 0,
            'is_active' => true,
        ]);

        $inStock->categories()->attach($category->id);
        $outOfStock->categories()->attach($category->id);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('https://royafashion.ir/category/bags', $xml);
        $this->assertStringContainsString('https://royafashion.ir/product/leather-bag', $xml);
        $this->assertStringContainsString('https://royafashion.ir/product/sold-out-bag', $xml);
        $this->assertStringNotContainsString('/c/bags', $xml);
        $this->assertStringNotContainsString('/products/' . $inStock->id, $xml);
    }

    public function test_unknown_category_and_inactive_category_return_404_without_seo(): void
    {
        $this->get('/category/missing-category')->assertStatus(404);

        $inactive = Category::create([
            'name' => 'غیرفعال',
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $response = $this->get("/category/{$inactive->slug}")->assertStatus(404);

        $this->assertStringNotContainsString('application/ld+json', $response->getContent());
    }
}
