<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\RoyaProductSocialPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoyaProductSocialPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.product_published_webhook_url' => 'https://n8n.example.test/webhook', 'app.url' => 'https://shop.example.test']);
        Storage::fake('public');
        config(['filesystems.disks.public.url' => 'https://shop.example.test/storage']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 200)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['admin']);
    }

    public static function imageCounts(): array
    {
        return [[1], [5]];
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name.'.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII='
        ));
    }

    #[DataProvider('imageCounts')]
    public function test_create_uploads_all_images_before_sending_one_webhook(int $count): void
    {
        $category = Category::create(['name' => 'کیف زنانه', 'slug' => 'bags']);
        $created = $this->postJson('/api/admin/products', [
            'name' => 'کیف چرمی', 'slug' => 'leather-bag', 'price' => 2450000,
            'description' => 'توضیحات فارسی محصول', 'category_ids' => [$category->id],
            'publish_image_count' => $count,
        ])->assertCreated()->json();
        Http::assertNothingSent();

        for ($i = 0; $i < $count; $i++) {
            $data = ['image' => $this->image("image-{$i}.jpg"), 'alt_text' => 'تصویر کیف'];
            if ($i === $count - 1) {
                $data['publish_token'] = $created['publish_token'];
            }
            $this->postJson("/api/admin/products/{$created['id']}/images", $data)->assertCreated();
            if ($i < $count - 1) {
                Http::assertNothingSent();
            }
        }

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($count) {
            $payload = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('product.published', $payload['event']);
            $this->assertSame('کیف چرمی', $payload['product']['name']);
            $this->assertSame('توضیحات فارسی محصول', $payload['product']['description']);
            $this->assertSame('کیف زنانه', $payload['product']['category']);
            $this->assertSame('IRT', $payload['product']['currency']);
            $this->assertSame('https://shop.example.test/products/leather-bag', $payload['product']['product_url']);
            $this->assertCount($count, $payload['product']['images']);
            $this->assertSame($payload['product']['images'][0]['url'], $payload['product']['image_url']);
            $this->assertTrue($payload['product']['images'][0]['is_primary']);
            foreach ($payload['product']['images'] as $index => $image) {
                $this->assertSame($index + 1, $image['sort_order']);
                $this->assertSame('تصویر کیف', $image['alt_text']);
                $this->assertStringStartsWith('https://shop.example.test/storage/products/', $image['url']);
            }
            return $request->method() === 'POST' && $request->url() === 'https://n8n.example.test/webhook';
        });

        // A replay cannot send a second notification.
        app(RoyaProductSocialPublisher::class)->complete(Product::findOrFail($created['id']), $created['publish_token']);
        Http::assertSentCount(1);
    }

    public function test_order_primary_fallback_and_fresh_database_values(): void
    {
        $product = Product::create(['name' => 'Old', 'slug' => 'ordered', 'price' => 100]);
        $last = $product->images()->create(['path' => 'products/last.jpg', 'sort_order' => 8, 'is_primary' => true]);
        $first = $product->images()->create(['path' => 'products/first.jpg', 'sort_order' => 1]);
        $second = $product->images()->create(['path' => 'products/second.jpg', 'sort_order' => 1]);
        $product->load('images');
        Product::whereKey($product->id)->update(['name' => 'جدید']);

        app(RoyaProductSocialPublisher::class)->publish($product);
        Http::assertSent(function ($request) use ($first, $second, $last) {
            $this->assertSame([$first->id, $second->id, $last->id], array_column($request['product']['images'], 'id'));
            $this->assertSame('https://shop.example.test/storage/products/last.jpg', $request['product']['image_url']);
            $this->assertSame('جدید', $request['product']['name']);
            return true;
        });
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 200)]);
        $last->update(['is_primary' => false]);
        app(RoyaProductSocialPublisher::class)->publish($product);
        Http::assertSent(fn ($request) => $request['product']['image_url'] === 'https://shop.example.test/storage/products/first.jpg');
    }

    public function test_no_images_skips_and_logs_without_publishing(): void
    {
        Log::spy();
        $this->postJson('/api/admin/products', ['name' => 'Empty', 'slug' => 'empty', 'price' => 10, 'publish_image_count' => 0])
            ->assertCreated()->assertJsonPath('publish_token', null);
        app(RoyaProductSocialPublisher::class)->publish(Product::first());
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->with('Product webhook skipped: no images selected.', \Mockery::any())->once();
        Log::shouldHaveReceived('info')->with('Product webhook skipped: no stored images.', \Mockery::any())->once();
    }

    public static function failures(): array
    {
        return [['timeout'], ['http500']];
    }

    #[DataProvider('failures')]
    public function test_webhook_errors_do_not_fail_product_or_image_save(string $failure): void
    {
        Log::spy();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(function () use ($failure) {
            if ($failure === 'timeout') {
                throw new ConnectionException('Timed out');
            }
            return Http::response([], 500);
        });
        $created = $this->postJson('/api/admin/products', ['name' => 'Saved', 'slug' => 'saved', 'price' => 10, 'publish_image_count' => 1])
            ->assertCreated()->json();
        $image = $this->postJson("/api/admin/products/{$created['id']}/images", [
            'image' => $this->image('saved.jpg'), 'publish_token' => $created['publish_token'],
        ])->assertCreated()->json();
        $this->assertDatabaseHas('products', ['id' => $created['id']]);
        $this->assertDatabaseHas('product_images', ['id' => $image['id']]);
        Storage::disk('public')->assertExists($image['path']);
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_early_completion_and_old_product_upload_do_not_publish(): void
    {
        $created = $this->postJson('/api/admin/products', ['name' => 'New', 'slug' => 'new', 'price' => 10, 'publish_image_count' => 2])
            ->assertCreated()->json();
        $this->postJson("/api/admin/products/{$created['id']}/images", [
            'image' => $this->image('one.jpg'), 'publish_token' => $created['publish_token'],
        ])->assertCreated();
        Http::assertNothingSent();
        $old = Product::create(['name' => 'Old', 'slug' => 'old', 'price' => 20]);
        $image = $this->postJson("/api/admin/products/{$old->id}/images", [
            'image' => $this->image('old.jpg'), 'publish_token' => $created['publish_token'],
        ])->assertCreated()->json();
        $this->putJson("/api/admin/products/{$old->id}", ['name' => 'Edited', 'slug' => 'old', 'price' => 30])->assertOk();
        $this->patchJson("/api/admin/products/{$old->id}/images/{$image['id']}/primary")->assertOk();
        $this->putJson("/api/admin/products/{$old->id}/images/reorder", ['image_ids' => [$image['id']]])->assertOk();
        $this->postJson("/api/admin/products/{$created['id']}/images", [
            'image' => UploadedFile::fake()->create('invalid.txt', 1, 'text/plain'),
            'publish_token' => $created['publish_token'],
        ])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
