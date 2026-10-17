<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\CreateWalletDetailJob;
use App\Jobs\LineWebhookJob;
use App\Jobs\NotificationFCM;
use App\Jobs\SendLineMessage;
use App\Jobs\WalletUserRegister;
use Tests\TestCase;

class JobRetryConfigurationTest extends TestCase
{
    public function test_line_webhook_job_declares_retry_properties(): void
    {
        $job = new LineWebhookJob(['events' => []]);

        $this->assertSame(3, $job->tries);
        $this->assertSame([5, 30], $job->backoff);
    }

    public function test_notification_fcm_job_declares_retry_properties(): void
    {
        $job = new NotificationFCM(1, 2, 'hello');

        $this->assertSame(3, $job->tries);
        $this->assertSame([5, 30], $job->backoff);
    }

    public function test_wallet_user_register_job_declares_retry_properties(): void
    {
        $job = new WalletUserRegister(['wallet' => ['id' => 1]]);

        $this->assertSame(3, $job->tries);
        $this->assertSame([5, 30], $job->backoff);
    }

    public function test_create_wallet_detail_job_declares_retry_properties(): void
    {
        $job = new CreateWalletDetailJob(1, 2, []);

        $this->assertSame(3, $job->tries);
        $this->assertSame([5, 30], $job->backoff);
    }

    public function test_send_line_message_job_declares_retry_properties(): void
    {
        $job = new SendLineMessage(['to' => 'U1', 'message' => 'hi']);

        $this->assertSame(3, $job->tries);
        $this->assertSame([5, 30], $job->backoff);
    }
}
