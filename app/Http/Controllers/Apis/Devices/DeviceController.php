<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Devices;

use App\Domain\Device\Services\DeviceService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Devices\DeviceStoreRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends ApiController
{
    /**
     * 保留方法，不支援建立頁面。
     *
     * @return JsonResponse
     */
    public function create(): JsonResponse
    {
        return $this->response()->errorBadRequest('Method not supported');
    }

    /**
     * 取得目前帳本成員的裝置清單。
     *
     * @param  Request  $request
     * @param  DeviceService  $deviceService
     * @return JsonResponse
     */
    public function index(Request $request, DeviceService $deviceService): JsonResponse
    {
        $owner = (array) data_get($request->input('wallet_user', []), (string) $request->route('wallet', 1), []);

        return $this->response()->success($deviceService->index($owner));
    }

    /**
     * 建立或更新裝置 token。
     *
     * @param  DeviceStoreRequest  $request
     * @param  DeviceService  $deviceService
     * @return JsonResponse
     */
    public function store(DeviceStoreRequest $request, DeviceService $deviceService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $deviceService->store($validated);

        return $this->response()->success();
    }

    /**
     * 取得單一裝置資訊。
     *
     * @param  int  $device
     * @return JsonResponse
     */
    public function show(int $device): JsonResponse
    {
        return $this->response()->success(['id' => $device]);
    }

    /**
     * 保留方法，不支援編輯頁面。
     *
     * @param  int  $device
     * @return JsonResponse
     */
    public function edit(int $device): JsonResponse
    {
        return $this->response()->errorBadRequest('Method not supported');
    }

    /**
     * 更新裝置資料。
     *
     * @param  int  $device
     * @param  Request  $request
     * @param  DeviceService  $deviceService
     * @return JsonResponse
     */
    public function update(int $device, Request $request, DeviceService $deviceService): JsonResponse
    {
        $deviceService->store($request->all());

        return $this->response()->success();
    }

    /**
     * 刪除裝置資料。
     *
     * @param  int  $device
     * @param  DeviceService  $deviceService
     * @return JsonResponse
     */
    public function destroy(int $device, DeviceService $deviceService): JsonResponse
    {
        $deviceService->destroy($device);

        return $this->response()->success();
    }
}
