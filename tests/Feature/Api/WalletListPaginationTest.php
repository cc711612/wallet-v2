<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-19：帳本列表 per_page 上限 100。
 */
class WalletListPaginationTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    public function test_per_page_is_capped_at_100(): void
    {
        $jwt = $this->userJwt($this->createUser(1, 'roy'));

        $response = $this->withToken($jwt)->getJson('/api/wallet?per_page=100000')->assertOk();

        $this->assertSame(100, (int) $response->json('data.paginate.per_page'), (string) $response->getContent());
    }

    public function test_normal_per_page_is_kept(): void
    {
        $jwt = $this->userJwt($this->createUser(1, 'roy'));

        $response = $this->withToken($jwt)->getJson('/api/wallet?per_page=20')->assertOk();

        $this->assertSame(20, (int) $response->json('data.paginate.per_page'));
    }
}
