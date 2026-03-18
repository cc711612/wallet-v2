<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Options;

use App\Docs\OpenApiSchemas;
use App\Domain\Option\Services\OptionService;
use App\Http\Controllers\ApiController;
use App\Http\Resources\Options\ExchangeRateResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OptionController extends ApiController
{
    /**
     * 取得指定幣別匯率資訊。
     */
    #[Response(200, '取得匯率成功', type: 'array{status: true, code: 200, message: string, data: '.OpenApiSchemas::OPTION_EXCHANGE_RATE.'}')]
    #[Response(400, '取得匯率失敗', type: 'array{status: false, code: 400, message: string, data: array<string,mixed>|object}')]
    public function exchangeRate(Request $request, OptionService $optionService): JsonResponse
    {
        $unit = (string) $request->query('unit', 'TWD');

        try {
            return $this->response()->success(new ExchangeRateResource($optionService->exchangeRate($unit)));
        } catch (RuntimeException $exception) {
            return $this->response()->errorBadRequest($exception->getMessage());
        }
    }

    /**
     * 取得分類選項清單。
     */
    #[Response(200, '取得分類成功', type: 'array{status: true, code: 200, message: string, data: array<int, array<string,mixed>>}')]
    public function category(OptionService $optionService): JsonResponse
    {
        return $this->response()->success($optionService->categories());
    }
}
