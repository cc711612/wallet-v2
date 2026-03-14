<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;
use App\Support\JwtTokenService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateWalletDetailJobService
{
    /**
     * 建立帳本明細工作服務。
     *
     * @return void
     */
    public function __construct(
        private WalletJobRepositoryInterface $walletJobRepository,
        private JwtTokenService $jwtTokenService,
    ) {}

    /**
     * 建立公費支出明細。
     *
     * @param  array<string, mixed>  $params
     */
    public function createGeneralExpenseDetail(int $userId, int $walletId, array $params): void
    {
        $user = $this->walletJobRepository->findUserById($userId);
        if ($user === null) {
            return;
        }

        $walletUser = $this->walletJobRepository->findWalletUserByWalletAndUser($walletId, $userId)
            ?? $this->walletJobRepository->findAdminWalletUserByWalletId($walletId);

        if ($walletUser === null) {
            return;
        }

        $payload = [
            'type' => 2,
            'symbol_operation_type_id' => 2,
            'title' => (string) Arr::get($params, 'title', ''),
            'value' => (int) Arr::get($params, 'amount', 0),
            'unit' => (string) Arr::get($params, 'unit', 'TWD'),
            'select_all' => true,
            'payment_wallet_user_id' => (int) ($walletUser['id'] ?? 0),
            'date' => (string) Arr::get($params, 'date', now()->format('Y-m-d')),
            'category_id' => Arr::get($params, 'categoryId'),
        ];

        $jwt = $this->jwtTokenService->makeUserJwt($user);
        $url = rtrim((string) config('app.url'), '/').'/api/wallet/'.$walletId.'/detail';
        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($jwt)
            ->post($url, $payload);

        if ($response->successful()) {
            return;
        }

        Log::warning('CreateWalletDetailJobService API fallback to direct DB insert', [
            'status' => $response->status(),
            'body' => $response->body(),
            'wallet_id' => $walletId,
            'user_id' => $userId,
        ]);

        $this->createDirect($userId, $walletId, $walletUser, $params);
    }

    /**
     * 直接寫入資料庫建立帳本明細。
     *
     * @param  array<string, mixed>  $walletUser
     * @param  array<string, mixed>  $params
     */
    private function createDirect(int $userId, int $walletId, array $walletUser, array $params): void
    {

        $detailId = $this->walletJobRepository->createWalletDetail([
            'wallet_id' => $walletId,
            'category_id' => Arr::get($params, 'categoryId'),
            'type' => 2,
            'payment_wallet_user_id' => (int) ($walletUser['id'] ?? 0),
            'title' => (string) Arr::get($params, 'title', ''),
            'symbol_operation_type_id' => 2,
            'select_all' => 1,
            'is_personal' => 0,
            'value' => (int) Arr::get($params, 'amount', 0),
            'unit' => (string) Arr::get($params, 'unit', 'TWD'),
            'rates' => null,
            'date' => (string) Arr::get($params, 'date', now()->format('Y-m-d')),
            'note' => null,
            'splits' => [],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $walletUserIds = $this->walletJobRepository->listWalletUserIds($walletId);
        if ($walletUserIds !== []) {
            $this->walletJobRepository->syncDetailUsers($detailId, $walletUserIds);
        }
    }
}
