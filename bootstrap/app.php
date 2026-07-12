<?php

use App\Http\Middleware\VerifyApi;
use App\Http\Middleware\VerifyLineSignature;
use App\Http\Middleware\VerifyWalletMemberApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 僅信任私有網段的 proxy（docker 內網 nginx / VM 外層 nginx），
        // 讓 X-Forwarded-Proto/For 生效，url() 與 secure cookie 才能正確判定 https
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        $middleware->alias([
            'VerifyApi' => VerifyApi::class,
            'VerifyWalletMemberApi' => VerifyWalletMemberApi::class,
            'VerifyLineSignature' => VerifyLineSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
