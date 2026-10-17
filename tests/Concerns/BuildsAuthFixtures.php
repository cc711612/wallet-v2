<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Support\JwtTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 認證／授權測試共用：在 sqlite 建立使用者、帳本、帳本成員，並以正式流程簽發 JWT。
 */
trait BuildsAuthFixtures
{
    protected function createUser(int $id, string $account, string $token = ''): array
    {
        $user = [
            'id' => $id,
            'name' => 'user-'.$id,
            'account' => $account,
            'password' => Hash::make('secret'),
            'token' => $token !== '' ? $token : 'user-token-'.$id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('users')->insert($user);

        return $user;
    }

    protected function createWallet(int $id, int $ownerUserId): void
    {
        DB::table('wallets')->insert([
            'id' => $id,
            'user_id' => $ownerUserId,
            'code' => 'W'.str_pad((string) $id, 7, '0', STR_PAD_LEFT),
            'title' => 'wallet-'.$id,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createWalletUser(int $id, int $walletId, ?int $userId, bool $isAdmin = false, string $token = ''): array
    {
        $walletUser = [
            'id' => $id,
            'wallet_id' => $walletId,
            'user_id' => $userId,
            'name' => 'member-'.$id,
            'token' => $token !== '' ? $token : 'member-token-'.$id,
            'is_admin' => $isAdmin ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('wallet_users')->insert($walletUser);

        return $walletUser;
    }

    protected function userJwt(array $user): string
    {
        return app(JwtTokenService::class)->makeUserJwt($user);
    }

    /**
     * 以任意 header／payload／金鑰拼出 JWT，用來模擬偽造的 token。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $header
     */
    protected function craftJwt(array $payload, ?string $key = null, array $header = ['typ' => 'JWT', 'alg' => 'HS256']): string
    {
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $headerEncoded = $encode((string) json_encode($header));
        $payloadEncoded = $encode((string) json_encode($payload));
        $signature = $key === null ? '' : $encode(hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $key, true));

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signature;
    }
}
