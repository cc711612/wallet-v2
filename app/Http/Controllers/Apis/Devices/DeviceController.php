<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apis\Devices;

use App\Docs\OpenApiSchemas;
use App\Domain\Device\Services\DeviceService;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Apis\Devices\DeviceStoreRequest;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends ApiController
{
    /**
     * 保留方法，不支援建立頁面。
     */
    #[Response(200, '不支援此方法', type: 'array{status: false, code: 400, message: string, data: array<string,mixed>|object}')]
    public function create(): JsonResponse
    {
        return $this->response()->errorBadRequest('Method not supported');
    }

    /**
     * 取得目前帳本成員的裝置清單。
     */
    #[Response(200, '取得裝置清單成功', type: 'array{status: true, code: 200, message: string, data: array{devices: array<int, '.OpenApiSchemas::DEVICE.'>}}')]
    public function index(Request $request, DeviceService $deviceService): JsonResponse
    {
        $owner = (array) data_get($request->input('wallet_user', []), (string) $request->route('wallet', 1), []);

        return $this->response()->success(['devices' => $deviceService->index($owner)]);
    }

    /**
     * 建立或更新裝置 token。
     */
    #[Response(200, '儲存裝置成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    public function store(DeviceStoreRequest $request, DeviceService $deviceService): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $deviceService->store($validated);

        return $this->response()->success();
    }

    /**
     * 取得單一裝置資訊。
     */
    #[Response(200, '取得裝置成功', type: 'array{status: true, code: 200, message: string, data: array{id:int}}')]
    public function show(int $device): JsonResponse
    {
        return $this->response()->success(['id' => $device]);
    }

    /**
     * 保留方法，不支援編輯頁面。
     */
    #[Response(200, '不支援此方法', type: 'array{status: false, code: 400, message: string, data: array<string,mixed>|object}')]
    public function edit(int $device): JsonResponse
    {
        return $this->response()->errorBadRequest('Method not supported');
    }

    /**
     * 更新裝置資料。
     */
    #[Response(200, '更新裝置成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    public function update(int $device, Request $request, DeviceService $deviceService): JsonResponse
    {
        $deviceService->store($request->all());

        return $this->response()->success();
    }

    /**
     * 刪除裝置資料。
     */
    #[Response(200, '刪除裝置成功', type: 'array{status: true, code: 200, message: string, data: array<string,mixed>|object}')]
    public function destroy(int $device, DeviceService $deviceService): JsonResponse
    {
        $deviceService->destroy($device);

        return $this->response()->success();
    }
}
