<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Options;

use App\Domain\Option\Services\OptionService;
use App\Http\Controllers\ApiController;
use App\Http\Resources\Options\ExchangeRateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OptionController extends ApiController
{
    /**
     * 取得指定幣別匯率資訊。
     *
     * @param  Request  $request
     * @param  OptionService  $optionService
     * @return JsonResponse
     */
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
     *
     * @param  OptionService  $optionService
     * @return JsonResponse
     */
    public function category(OptionService $optionService): JsonResponse
    {
        return $this->response()->success($optionService->categories());
    }
}
