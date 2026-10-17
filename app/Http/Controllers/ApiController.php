<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Wallet\Exceptions\WalletDetailBusinessException;
use App\Support\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class ApiController extends Controller
{
    /**
     * 取得共用回應物件。
     */
    protected function response(): Response
    {
        return app(Response::class);
    }

    /**
     * 回給 client 的錯誤訊息：只有業務例外（service 主動丟的 RuntimeException、
     * WalletDetailBusinessException、abort()）才原樣回傳；其他例外（QueryException、
     * 連線錯誤等，可能含 SQL／主機資訊）一律 report() 後回固定訊息。
     */
    protected function clientMessage(Throwable $exception): string
    {
        if (
            $exception instanceof WalletDetailBusinessException
            || $exception instanceof HttpExceptionInterface
            || $exception::class === RuntimeException::class
        ) {
            return $exception->getMessage();
        }

        report($exception);

        return '系統錯誤，請稍後再試';
    }
}
