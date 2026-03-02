<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class Response
{
    /**
     * @param  JsonResource|array<string, mixed>|null  $data
     * @param  array<string, string>  $headers
     */
    public function success(JsonResource|array|null $data = null, string $message = '', int $code = HttpResponse::HTTP_OK, array $headers = []): JsonResponse
    {
        /** @var array<string, mixed>|null $resolvedData */
        $resolvedData = $data instanceof JsonResource
            ? $data->resolve(request())
            : $data;

        return response()->json(
            $this->formatData($resolvedData, $message, $code),
            $this->transportStatusCode($code),
            $headers
        );
    }

    public function errorBadRequest(string $message = ''): JsonResponse
    {
        return $this->success(null, $message, HttpResponse::HTTP_BAD_REQUEST);
    }

    public function errorInternal(string $message = ''): JsonResponse
    {
        return $this->success(null, $message, HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{status:bool, code:int, message:string, data:object|array<string, mixed>}
     */
    private function formatData(?array $data, string $message, int $code): array
    {
        return [
            'status' => $code < 400,
            'code' => $code,
            'message' => $message,
            'data' => $data ?? (object) [],
        ];
    }

    private function transportStatusCode(int $statusCode): int
    {
        if ($statusCode === HttpResponse::HTTP_BAD_REQUEST) {
            return HttpResponse::HTTP_OK;
        }

        return $statusCode;
    }
}
