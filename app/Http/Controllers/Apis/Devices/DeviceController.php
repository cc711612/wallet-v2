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
    public function create(): JsonResponse
    {
        return $this->response()->errorBadRequest('Method not supported');
    }

    public function index(Request $request, DeviceService $deviceService): JsonResponse
    {
        $owner = (array) data_get($request->input('wallet_user', []), (string) $request->route('wallet', 1), []);

        return $this->response()->success($deviceService->index($owner));
    }

    public function store(DeviceStoreRequest $request, DeviceService $deviceService): JsonResponse
    {
        $validated = $request->validated();

        $deviceService->store($validated);

        return $this->response()->success();
    }

    public function show(int $device): JsonResponse
    {
        return $this->response()->success(['id' => $device]);
    }

    public function edit(int $device): JsonResponse
    {
        return $this->response()->errorBadRequest('Method not supported');
    }

    public function update(int $device, Request $request, DeviceService $deviceService): JsonResponse
    {
        $deviceService->store($request->all());

        return $this->response()->success();
    }

    public function destroy(int $device, DeviceService $deviceService): JsonResponse
    {
        $deviceService->destroy($device);

        return $this->response()->success();
    }
}
