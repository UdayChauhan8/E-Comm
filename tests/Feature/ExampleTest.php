<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_products_returns_successful_response()
    {
        $response = $this->getJson('/api/products');

        $response->assertOk();
    }
}
