<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mockery;
use App\Services\GuardianApiService;
use Illuminate\Support\Facades\Cache;

class RssFeedControllerTest extends TestCase
{
    public function test_valid_section()
    {
        $response = $this->get('/business');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/rss+xml');
    }

    public function test_invalid_section()
    {
        $response = $this->get('/MOVIES!');
        $response->assertStatus(400);
    }

    public function test_caching()
    {
        Cache::shouldReceive('has')->once()->andReturn(true);
        Cache::shouldReceive('get')->once()->andReturn('<rss></rss>');

        $response = $this->get('/business');
        $response->assertStatus(200);
    }
}