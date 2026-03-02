<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Entities;

final class WalletDetail
{
    private int $walletId;

    private int $walletUserId;

    private int $type;

    private int $symbolOperationTypeId;

    private string $title;

    private float $value;

    private bool $selectAll;

    private bool $isPersonal;

    private ?int $paymentWalletUserId;

    /** @var array<int, int> */
    private array $users;

    /** @var array<int, array{user_id:int, value:float|int}> */
    private array $splits;

    private string $unit;

    private ?float $rates;

    private string $date;

    private ?string $note;

    private ?int $categoryId;

    /**
     * @param  array<string, mixed>  $payload
     * @return void
     */
    private function __construct(array $payload)
    {
        $this->walletId = (int) ($payload['wallet'] ?? 0);
        $this->walletUserId = (int) ($payload['wallet_user_id'] ?? 0);
        $this->type = (int) ($payload['type'] ?? 0);
        $this->symbolOperationTypeId = (int) ($payload['symbol_operation_type_id'] ?? 0);
        $this->title = (string) ($payload['title'] ?? '');
        $this->value = (float) ($payload['value'] ?? 0);
        $this->selectAll = (bool) ($payload['select_all'] ?? false);
        $this->isPersonal = (bool) ($payload['is_personal'] ?? false);
        $this->paymentWalletUserId = isset($payload['payment_wallet_user_id']) ? (int) $payload['payment_wallet_user_id'] : null;
        $this->users = array_values(array_unique(array_map('intval', (array) ($payload['users'] ?? []))));
        $this->splits = is_array($payload['splits'] ?? null) ? $payload['splits'] : [];
        $this->unit = (string) ($payload['unit'] ?? 'TWD');
        $this->rates = isset($payload['rates']) ? (float) $payload['rates'] : null;
        $this->date = (string) ($payload['date'] ?? now()->toDateString());
        $this->note = isset($payload['note']) ? (string) $payload['note'] : null;
        $this->categoryId = isset($payload['category_id']) ? (int) $payload['category_id'] : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    public function walletId(): int
    {
        return $this->walletId;
    }

    public function walletUserId(): int
    {
        return $this->walletUserId;
    }

    public function type(): int
    {
        return $this->type;
    }

    public function symbolOperationTypeId(): int
    {
        return $this->symbolOperationTypeId;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function selectAll(): bool
    {
        return $this->selectAll;
    }

    /**
     * @return array<int, int>
     */
    public function users(): array
    {
        return $this->users;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistenceAttributes(): array
    {
        return [
            'wallet_id' => $this->walletId,
            'type' => $this->type,
            'payment_wallet_user_id' => $this->paymentWalletUserId,
            'title' => $this->title,
            'symbol_operation_type_id' => $this->symbolOperationTypeId,
            'select_all' => $this->selectAll,
            'is_personal' => $this->isPersonal,
            'value' => $this->value,
            'unit' => $this->unit,
            'rates' => $this->rates,
            'date' => $this->date,
            'note' => $this->note,
            'category_id' => $this->categoryId,
            'created_by' => $this->walletUserId,
            'updated_by' => $this->walletUserId,
            'users' => $this->users,
            'splits' => $this->splits,
        ];
    }
}
