<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 真實走 VerifyApi / VerifyWalletMemberApi（不使用 fakeApiAuthentication）。
 */
class MiddlewareAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_api_route_without_token_returns_401(): void
    {
        $this->getJson('/api/wallet')->assertStatus(401);
    }

    public function test_verify_api_route_with_unknown_member_token_returns_401(): void
    {
        $this->getJson('/api/wallet?member_token=not-exists')->assertStatus(401);
    }

    public function test_wallet_member_route_without_token_returns_401(): void
    {
        $this->getJson('/api/wallet/1/detail')->assertStatus(401);
    }

    public function test_wallet_member_route_with_unknown_member_token_returns_401(): void
    {
        $this->getJson('/api/wallet/1/detail?member_token=not-exists')->assertStatus(401);
    }
}
