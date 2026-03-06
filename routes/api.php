<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
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
        Route::post('/login', [LoginController::class, 'login'])->name('login');
        Route::match(['get', 'post'], '/cache', [LoginController::class, 'cache'])->name('cache');
        Route::group(['as' => 'register.', 'prefix' => 'register'], function (): void {
            Route::post('/', [RegisterController::class, 'register'])->name('index');
            Route::post('/token', [RegisterController::class, 'registerByToken'])->name('token');
        });
        Route::group(['as' => 'thirdParty.', 'prefix' => 'thirdParty'], function (): void {
            Route::post('/login', [LoginController::class, 'thirdPartyLogin'])->name('login');
            Route::post('/checkBind', [SocialController::class, 'checkBind'])->name('checkBind');
        });
    });

    Route::group(['as' => 'option.', 'prefix' => 'option'], function (): void {
        Route::get('/exchangeRate', [OptionController::class, 'exchangeRate'])->name('exchangeRate');
        Route::get('/category', [OptionController::class, 'category'])->name('category');
    });

    Route::group(['as' => 'wallet.', 'prefix' => 'wallet'], function (): void {
        Route::match(['get', 'post'], '/user', [WalletUserController::class, 'index'])->name('user');
        Route::group(['as' => 'auth.', 'prefix' => 'auth'], function (): void {
            Route::post('/login', [WalletLoginController::class, 'login'])->name('login');
            Route::post('/login/token', [WalletLoginController::class, 'token'])->name('token');
            Route::post('/register', [WalletRegisterController::class, 'register'])->name('register');
            Route::post('/register/batch', [WalletRegisterController::class, 'registerBatch'])->name('batch');
        });
    });

    Route::group(['as' => 'webhook.', 'prefix' => 'webhook'], function (): void {
        Route::group(['as' => 'line.', 'prefix' => 'line'], function (): void {
            Route::any('/', [LineController::class, 'store'])->name('store');
            Route::any('/notify', [LineController::class, 'notify'])->name('notify');
            Route::any('/notifyBind', [LineController::class, 'notifyBind'])->middleware(['VerifyApi'])->name('notifyBind');
            Route::any('/notifyToken', [LineController::class, 'notifyToken'])->middleware(['VerifyApi'])->name('notifyToken');
            Route::any('/notifySendMessage', [LineController::class, 'notifySendMessage'])->middleware(['VerifyApi'])->name('notifySendMessage');
        });
    });

    Route::group(['as' => 'log.', 'prefix' => 'log'], function (): void {
        Route::group(['as' => 'front.', 'prefix' => 'front'], function (): void {
            Route::post('/normal', [FrontLogController::class, 'normal'])->name('normal');
            Route::post('/serious', [FrontLogController::class, 'serious'])->name('serious');
        });
    });

    Route::group(['middleware' => ['VerifyApi']], function (): void {
        Route::group(['as' => 'auth.', 'prefix' => 'auth'], function (): void {
            Route::group(['as' => 'thirdParty.', 'prefix' => 'thirdParty'], function (): void {
                Route::post('/bind', [SocialController::class, 'bind'])->name('bind');
                Route::post('/unBind', [SocialController::class, 'unBind'])->name('unBind');
            });
            Route::post('/user/thirdParty', [UserController::class, 'socials'])->name('user.thirdParty');
            Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
        });

        Route::group(['as' => 'wallet.', 'prefix' => 'wallet'], function (): void {
            Route::post('/list', [WalletController::class, 'index'])->name('list');
            Route::get('/', [WalletController::class, 'index'])->name('index');
            Route::post('/bind', [WalletController::class, 'bind'])->name('bind');
        });

        Route::resource('wallet', WalletController::class)->only(['store', 'update', 'destroy']);
    });

    Route::group(['middleware' => ['VerifyWalletMemberApi']], function (): void {
        Route::resource('device', DeviceController::class)->names('device');
        Route::group(['as' => 'wallet.', 'prefix' => 'wallet'], function (): void {
            Route::group(['as' => 'user.', 'prefix' => 'user'], function (): void {
                Route::put('{wallet_users_id}', [WalletUserController::class, 'update'])->name('update');
            });

            Route::group(['prefix' => '{wallet}'], function (): void {
                Route::group(['prefix' => 'detail', 'as' => 'detail.'], function (): void {
                    Route::post('/list', [WalletDetailController::class, 'index'])->name('list');
                    Route::get('/', [WalletDetailController::class, 'index'])->name('index');
                    Route::match(['get', 'post'], '/{detail}', [WalletDetailController::class, 'show'])->name('show');
                    Route::put('/checkout', [WalletDetailController::class, 'checkout'])->name('checkout');
                    Route::put('/undo_checkout', [WalletDetailController::class, 'uncheckout'])->name('undoCheckout');
                });

                Route::post('/calculation', [WalletController::class, 'calculation'])->name('calculation');
                Route::resource('detail', WalletDetailController::class)->only(['store', 'update', 'destroy'])->names('detail');
                Route::group(['as' => 'user.', 'prefix' => 'user'], function (): void {
                    Route::delete('/{wallet_user_id}', [WalletUserController::class, 'destroy'])->name('destroy');
                });
            });
        });
    });
});

Route::get('/health', [HealthController::class, 'check'])->name('health');

Route::prefix('gemini')->group(function (): void {
    Route::post('/generate', [GeminiController::class, 'generateContent'])->name('generate');
    Route::post('/stream', [GeminiController::class, 'streamContent'])->name('stream');
    Route::post('/chat', [GeminiController::class, 'chat'])->name('chat');
    Route::get('/models', [GeminiController::class, 'listModels'])->name('models');
});

Route::fallback(function () {
    return response()->json([
        'code' => 404,
        'status' => false,
        'message' => '不支援此方法',
    ], 404);
});
