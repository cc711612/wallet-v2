<?php

declare(strict_types=1);

use App\Http\Controllers\Apis\Auth\LoginController;
use App\Http\Controllers\Apis\Auth\LogoutController;
use App\Http\Controllers\Apis\Auth\RegisterController;
use App\Http\Controllers\Apis\Devices\DeviceController;
use App\Http\Controllers\Apis\Logs\FrontLogController;
use App\Http\Controllers\Apis\Logs\LineController;
use App\Http\Controllers\Apis\Options\OptionController;
use App\Http\Controllers\Apis\Socials\SocialController;
use App\Http\Controllers\Apis\Users\UserController;
use App\Http\Controllers\Apis\Wallets\Auth\WalletLoginController;
use App\Http\Controllers\Apis\Wallets\Auth\WalletRegisterController;
use App\Http\Controllers\Apis\Wallets\WalletController;
use App\Http\Controllers\Apis\Wallets\WalletDetailController;
use App\Http\Controllers\Apis\Wallets\WalletUserController;
use App\Http\Controllers\GeminiController;
use Illuminate\Support\Facades\Route;

Route::group(['as' => 'api.'], function (): void {
    Route::group(['as' => 'auth.', 'prefix' => 'auth'], function (): void {
        Route::post('/login', [LoginController::class, 'login']);
        Route::match(['get', 'post'], '/cache', [LoginController::class, 'cache']);
        Route::group(['as' => 'register.', 'prefix' => 'register'], function (): void {
            Route::post('/', [RegisterController::class, 'register']);
            Route::post('/token', [RegisterController::class, 'registerByToken']);
        });
        Route::group(['as' => 'thirdParty.', 'prefix' => 'thirdParty'], function (): void {
            Route::post('/login', [LoginController::class, 'thirdPartyLogin']);
            Route::post('/checkBind', [SocialController::class, 'checkBind']);
        });
    });

    Route::group(['as' => 'option.', 'prefix' => 'option'], function (): void {
        Route::get('/exchangeRate', [OptionController::class, 'exchangeRate']);
        Route::get('/category', [OptionController::class, 'category']);
    });

    Route::group(['as' => 'wallet.', 'prefix' => 'wallet'], function (): void {
        Route::match(['get', 'post'], '/user', [WalletUserController::class, 'index']);
        Route::group(['as' => 'auth.', 'prefix' => 'auth'], function (): void {
            Route::post('/login', [WalletLoginController::class, 'login']);
            Route::post('/login/token', [WalletLoginController::class, 'token']);
            Route::post('/register', [WalletRegisterController::class, 'register']);
            Route::post('/register/batch', [WalletRegisterController::class, 'registerBatch']);
        });
    });

    Route::group(['as' => 'webhook.', 'prefix' => 'webhook'], function (): void {
        Route::group(['as' => 'line.', 'prefix' => 'line'], function (): void {
            Route::any('/', [LineController::class, 'store']);
            Route::any('/notify', [LineController::class, 'notify']);
            Route::any('/notifyBind', [LineController::class, 'notifyBind'])->middleware(['VerifyApi']);
            Route::any('/notifyToken', [LineController::class, 'notifyToken'])->middleware(['VerifyApi']);
            Route::any('/notifySendMessage', [LineController::class, 'notifySendMessage'])->middleware(['VerifyApi']);
        });
    });

    Route::group(['as' => 'log.', 'prefix' => 'log'], function (): void {
        Route::group(['as' => 'front.', 'prefix' => 'front'], function (): void {
            Route::post('/normal', [FrontLogController::class, 'normal']);
            Route::post('/serious', [FrontLogController::class, 'serious']);
        });
    });

    Route::group(['middleware' => ['VerifyApi']], function (): void {
        Route::group(['as' => 'auth.', 'prefix' => 'auth'], function (): void {
            Route::group(['as' => 'thirdParty.', 'prefix' => 'thirdParty'], function (): void {
                Route::post('/bind', [SocialController::class, 'bind']);
                Route::post('/unBind', [SocialController::class, 'unBind']);
            });
            Route::post('/user/thirdParty', [UserController::class, 'socials']);
            Route::post('/logout', [LogoutController::class, 'logout']);
        });

        Route::group(['as' => 'wallet.', 'prefix' => 'wallet'], function (): void {
            Route::post('/list', [WalletController::class, 'index']);
            Route::get('/', [WalletController::class, 'index']);
            Route::post('/bind', [WalletController::class, 'bind']);
        });

        Route::resource('wallet', WalletController::class)->only(['store', 'update', 'destroy']);
    });

    Route::group(['middleware' => ['VerifyWalletMemberApi']], function (): void {
        Route::resource('device', DeviceController::class);
        Route::group(['as' => 'wallet.', 'prefix' => 'wallet'], function (): void {
            Route::group(['as' => 'user.', 'prefix' => 'user'], function (): void {
                Route::put('{wallet_users_id}', [WalletUserController::class, 'update']);
            });

            Route::group(['prefix' => '{wallet}'], function (): void {
                Route::group(['prefix' => 'detail', 'as' => 'detail.'], function (): void {
                    Route::post('/list', [WalletDetailController::class, 'index']);
                    Route::get('/', [WalletDetailController::class, 'index']);
                    Route::match(['get', 'post'], '/{detail}', [WalletDetailController::class, 'show']);
                    Route::put('/checkout', [WalletDetailController::class, 'checkout']);
                    Route::put('/undo_checkout', [WalletDetailController::class, 'uncheckout']);
                });

                Route::post('/calculation', [WalletController::class, 'calculation']);
                Route::resource('detail', WalletDetailController::class)->only(['store', 'update', 'destroy']);
                Route::group(['as' => 'user.', 'prefix' => 'user'], function (): void {
                    Route::delete('/{wallet_user_id}', [WalletUserController::class, 'destroy']);
                });
            });
        });
    });
});

Route::prefix('gemini')->group(function (): void {
    Route::post('/generate', [GeminiController::class, 'generateContent']);
    Route::post('/stream', [GeminiController::class, 'streamContent']);
    Route::post('/chat', [GeminiController::class, 'chat']);
    Route::get('/models', [GeminiController::class, 'listModels']);
});

Route::fallback(function () {
    return response()->json([
        'code' => 404,
        'status' => false,
        'message' => '不支援此方法',
    ], 404);
});
