<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P0-16：token／code 查詢欄位要有 index。
 */
class IndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_columns_are_indexed(): void
    {
        $this->assertTrue(Schema::hasIndex('users', ['token']));
        $this->assertTrue(Schema::hasIndex('wallet_users', ['token']));
        $this->assertTrue(Schema::hasIndex('socials', ['social_type', 'social_type_value']));
        $this->assertTrue(Schema::hasIndex('wallets', ['code']));
    }
}
