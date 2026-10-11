<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GalleryNewProductsTest extends TestCase
{
    use RefreshDatabase;

    /* ────────────────────────────────────────────────────────────
     | Admin selection lifecycle
     | ──────────────────────────────────────────────────────────── */

    public function test_admin_can_select_a_product_for_the_new_arrivals_section(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/admin/products', [
            'name' => 'New arrival',
            'slug' => 'new-arrival',
            'price' => 100,
            'is_active' => true,
            'is_gallery_new' => true,
        ])->assertCreated();

        $this->assertTrue($response->json('is_gallery_new'));
        $this->assertNotNull($response->json('gallery_new_at'));

        $this->getJson('/api/products?gallery_new=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'new-arrival')
            ->assertJsonPath('data.0.is_gallery_new', true);
    }

    public function test_deselecting_a_product_clears_the_flag_and_timestamp(): void
    {
        $this->authenticate();

        $product = $this->createProduct([
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
        ]);

        $this->getJson('/api/products?gallery_new=1')->assertJsonCount(1, 'data');

        $this->putJson("/api/admin/products/{$product->id}", [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => 100,
            'is_active' => true,
            'is_gallery_new' => false,
        ])->assertOk();

        $product->refresh();

        $this->assertFalse($product->is_gallery_new);
        $this->assertNull($product->gallery_new_at);

        $this->getJson('/api/products?gallery_new=1')->assertJsonCount(0, 'data');
    }

    public function test_resaving_a_selected_product_keeps_its_original_timestamp(): void
    {
        $this->authenticate();

        $original = now()->subDays(5)->startOfSecond();
        $product = $this->createProduct([
            'is_gallery_new' => true,
            'gallery_new_at' => $original,
        ]);

        $this->putJson("/api/admin/products/{$product->id}", [
            'name' => 'Renamed arrival',
            'slug' => $product->slug,
            'price' => 120,
            'is_active' => true,
            'is_gallery_new' => true,
        ])->assertOk();

        $product->refresh();

        $this->assertTrue($product->is_gallery_new);
        $this->assertTrue($original->equalTo($product->gallery_new_at));
    }

    /* ────────────────────────────────────────────────────────────
     | Public homepage listing
     | ──────────────────────────────────────────────────────────── */

    public function test_new_arrivals_do_not_depend_on_published_at(): void
    {
        $this->createProduct([
            'slug' => 'unpublished-arrival',
            'published_at' => null,
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
        ]);

        $this->getJson('/api/products?gallery_new=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'unpublished-arrival');
    }

    public function test_new_arrivals_are_ordered_by_selection_time_newest_first(): void
    {
        $this->createProduct(['slug' => 'oldest-pick', 'is_gallery_new' => true, 'gallery_new_at' => now()->subDays(3)]);
        $this->createProduct(['slug' => 'newest-pick', 'is_gallery_new' => true, 'gallery_new_at' => now()->subDay()]);
        $this->createProduct(['slug' => 'middle-pick', 'is_gallery_new' => true, 'gallery_new_at' => now()->subDays(2)]);
        $this->createProduct(['slug' => 'not-selected', 'is_gallery_new' => false]);

        $response = $this->getJson('/api/products?gallery_new=1')->assertOk();

        $this->assertSame(
            ['newest-pick', 'middle-pick', 'oldest-pick'],
            array_column($response->json('data'), 'slug')
        );
    }

    public function test_new_arrivals_ignore_published_at_order(): void
    {
        // Newest by published_at but selected earlier must still come after the
        // more recently selected product regardless of publication date.
        $this->createProduct([
            'slug' => 'selected-first',
            'published_at' => now(),
            'is_gallery_new' => true,
            'gallery_new_at' => now()->subDays(2),
        ]);
        $this->createProduct([
            'slug' => 'selected-later',
            'published_at' => now()->subYear(),
            'is_gallery_new' => true,
            'gallery_new_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/products?gallery_new=1')->assertOk();

        $this->assertSame(
            ['selected-later', 'selected-first'],
            array_column($response->json('data'), 'slug')
        );
    }

    public function test_inactive_products_never_appear_in_the_section(): void
    {
        $this->createProduct([
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
            'is_active' => false,
        ]);

        $this->getJson('/api/products?gallery_new=1')->assertJsonCount(0, 'data');
    }

    public function test_featured_and_gallery_new_selections_are_independent(): void
    {
        $featuredOnly = $this->createProduct([
            'slug' => 'featured-only',
            'is_featured' => true,
            'is_gallery_new' => false,
        ]);
        $this->createProduct([
            'slug' => 'gallery-only',
            'is_featured' => false,
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
        ]);
        $both = $this->createProduct([
            'slug' => 'both-sections',
            'is_featured' => true,
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
        ]);

        $featuredSlugs = collect($this->getJson('/api/products?featured=1')->json('data'))
            ->pluck('slug')->sort()->values()->all();
        $this->assertSame(['both-sections', 'featured-only'], $featuredSlugs);

        $gallerySlugs = collect($this->getJson('/api/products?gallery_new=1')->json('data'))
            ->pluck('slug')->sort()->values()->all();
        $this->assertSame(['both-sections', 'gallery-only'], $gallerySlugs);

        // A featured product is not implicitly a new arrival and vice versa.
        $this->assertFalse($featuredOnly->is_gallery_new);
        $this->assertTrue($both->fresh()->is_featured);
    }

    public function test_gallery_new_flag_is_exposed_on_the_public_product_resource(): void
    {
        $this->createProduct([
            'slug' => 'published-piece',
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
        ]);

        $this->getJson('/api/products?gallery_new=1')
            ->assertOk()
            ->assertJsonPath('data.0.is_gallery_new', true);
    }

    public function test_gallery_new_flag_combines_with_a_category_filter(): void
    {
        $category = Category::create([
            'name' => 'Mantos',
            'slug' => 'mantos',
            'is_active' => true,
        ]);

        $selected = $this->createProduct([
            'slug' => 'selected-manto',
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
        ]);
        $selected->categories()->attach($category);

        $other = $this->createProduct([
            'slug' => 'selected-other',
            'is_gallery_new' => true,
            'gallery_new_at' => now(),
        ]);

        $this->getJson('/api/products?category=mantos&gallery_new=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'selected-manto');
    }

    public function test_featured_and_gallery_new_filters_do_not_leak_into_attribute_filters(): void
    {
        // Both params must be reserved; otherwise they would be treated as
        // attribute slugs and the request rejected with a validation error.
        $this->getJson('/api/products?featured=1&gallery_new=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /* ────────────────────────────────────────────────────────────
     | Helpers
     | ──────────────────────────────────────────────────────────── */

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Gallery piece',
            'slug' => 'gallery-piece-'.uniqid(),
            'price' => 100,
            'is_active' => true,
        ], $overrides));
    }
}
