<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticAssetCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_asset_responses_carry_long_lived_cache_headers(): void
    {
        $this->get('/images/placeholder.svg')
            ->assertHeader('Cache-Control', 'max-age=604800, public');
    }

    public function test_api_json_responses_keep_default_cache_policy(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private');
    }
}
