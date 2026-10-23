<?php

namespace App\Providers;

use App\Domain\Auth\Repositories\AuthServiceRepositoryInterface;
use App\Domain\Auth\Repositories\UserTokenRepositoryInterface;
use App\Domain\Device\Repositories\DeviceServiceRepositoryInterface;
use App\Domain\ExchangeRate\Repositories\ExchangeRateRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Notification\Repositories\NotifyV2RepositoryInterface;
use App\Domain\Option\Repositories\OptionServiceRepositoryInterface;
use App\Domain\Social\Repositories\SocialServiceRepositoryInterface;
use App\Domain\Wallet\Entities\WalletDetailEntity;
use App\Domain\Wallet\Repositories\WalletAuthServiceRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletDetailQueryRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletDetailRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletJobRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletMemberTokenRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletServiceRepositoryInterface;
use App\Domain\Wallet\Repositories\WalletUserServiceRepositoryInterface;
use App\Domain\Webhook\Repositories\LineWebhookJobRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\AuthServiceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\DeviceServiceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\ExchangeRateRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\LineWebhookJobRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\NotificationJobRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\NotifyV2Repository;
use App\Infrastructure\Persistence\Eloquent\Repositories\OptionServiceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\SocialServiceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\UserTokenRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletAuthServiceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletDetailQueryRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletDetailRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletJobRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletMemberTokenRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletServiceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\WalletUserServiceRepository;
use App\Observers\WalletDetailObserver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthServiceRepositoryInterface::class, AuthServiceRepository::class);
        $this->app->bind(UserTokenRepositoryInterface::class, UserTokenRepository::class);
        $this->app->bind(DeviceServiceRepositoryInterface::class, DeviceServiceRepository::class);
        $this->app->bind(OptionServiceRepositoryInterface::class, OptionServiceRepository::class);
        $this->app->bind(SocialServiceRepositoryInterface::class, SocialServiceRepository::class);
        $this->app->bind(WalletAuthServiceRepositoryInterface::class, WalletAuthServiceRepository::class);
        $this->app->bind(WalletDetailQueryRepositoryInterface::class, WalletDetailQueryRepository::class);
        $this->app->bind(WalletMemberTokenRepositoryInterface::class, WalletMemberTokenRepository::class);
        $this->app->bind(WalletDetailRepositoryInterface::class, WalletDetailRepository::class);
        $this->app->bind(WalletServiceRepositoryInterface::class, WalletServiceRepository::class);
        $this->app->bind(WalletUserServiceRepositoryInterface::class, WalletUserServiceRepository::class);
        $this->app->bind(WalletJobRepositoryInterface::class, WalletJobRepository::class);
        $this->app->bind(NotificationJobRepositoryInterface::class, NotificationJobRepository::class);
        $this->app->bind(NotifyV2RepositoryInterface::class, NotifyV2Repository::class);
        $this->app->bind(LineWebhookJobRepositoryInterface::class, LineWebhookJobRepository::class);
        $this->app->bind(ExchangeRateRepositoryInterface::class, ExchangeRateRepository::class);

        if (class_exists(Scramble::class)) {
            Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                        ->as('bearerAuth')
                        ->setDescription('Authorization header: Bearer <JWT>')
                );

                $openApi->secure(
                    SecurityScheme::apiKey('query', 'member_token')
                        ->as('memberToken')
                        ->setDescription('Wallet member token (query parameter)')
                );
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        WalletDetailEntity::observe(WalletDetailObserver::class);

        $this->configureRateLimiting();
    }

    /**
     * 登入類端點限流：同 IP＋同帳號（或帳本 code）每分鐘 10 次，同 IP 每分鐘 30 次。
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', static function (Request $request): array {
            $ip = (string) $request->ip();
            $identity = mb_strtolower((string) ($request->input('account') ?? $request->input('code') ?? $request->input('socialTypeValue') ?? ''));

            return [
                Limit::perMinute(10)->by('auth|'.$ip.'|'.$identity),
                Limit::perMinute(30)->by('auth-ip|'.$ip),
            ];
        });

        // 註冊／批次建訪客：同 IP 每分鐘 5 次、每小時 30 次（不分帳號或帳本 code，避免換值繞過）
        RateLimiter::for('register', static function (Request $request): array {
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(5)->by('register-min|'.$ip),
                Limit::perHour(30)->by('register-hour|'.$ip),
            ];
        });

        RateLimiter::for('front-log', static fn (Request $request): Limit => Limit::perMinute(30)->by('front-log|'.$request->ip()));
    }
}
