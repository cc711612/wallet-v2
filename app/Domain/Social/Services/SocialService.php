<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Repositories\SocialServiceRepositoryInterface;
use Illuminate\Support\Str;

class SocialService
{
    /**
     * @return void
     */
    public function __construct(private SocialServiceRepositoryInterface $socialRepository) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkBind(array $payload): array
    {
        $socialType = (string) ($payload['socialType'] ?? '');
        $socialTypeValue = (string) ($payload['socialTypeValue'] ?? '');

        if ($this->socialRepository->existsByTypeAndValue($socialType, $socialTypeValue)) {
            return ['action' => 'bind', 'token' => ''];
        }

        return [
            'action' => 'not bind',
            'token' => Str::random(32),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function bind(array $payload): array
    {
        return ['bound' => true, 'token' => (string) ($payload['token'] ?? '')];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function unBind(array $payload): array
    {
        return ['unbound' => true, 'socialType' => (string) ($payload['socialType'] ?? '')];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->socialRepository->listSocialsByUserId($userId);
    }
}
