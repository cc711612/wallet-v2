<?php

namespace App\Providers;

use App\Domain\Auth\Repositories\AuthServiceRepositoryInterface;
use App\Domain\Auth\Repositories\UserTokenRepositoryInterface;
use App\Domain\Device\Repositories\DeviceServiceRepositoryInterface;
use App\Domain\ExchangeRate\Repositories\ExchangeRateRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationJobRepositoryInterface;
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
    }
}
