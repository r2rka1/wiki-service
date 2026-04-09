<?php

namespace Tests\Feature\Internal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_endpoint_rejects_missing_secret(): void
    {
        $this->postJson('/api/internal/jobs/fetch')
            ->assertStatus(401);
    }

    public function test_internal_endpoint_rejects_wrong_secret(): void
    {
        $this->withHeaders([
            'X-Internal-Secret' => 'nope',
            'X-User-Id'         => '1',
        ])
            ->postJson('/api/internal/jobs/fetch')
            ->assertStatus(401);
    }

    public function test_internal_endpoint_requires_user_header(): void
    {
        $this->withHeaders([
            'X-Internal-Secret' => 'test-secret',
        ])
            ->postJson('/api/internal/jobs/fetch')
            ->assertStatus(400);
    }
}
