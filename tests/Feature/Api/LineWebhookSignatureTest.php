<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\LineWebhookJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LineWebhookSignatureTest extends TestCase
{
    private const CHANNEL_SECRET = 'test-channel-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['bot.line.channel_secret' => self::CHANNEL_SECRET]);
    }

    public function test_webhook_without_signature_header_is_rejected(): void
    {
        Queue::fake();

        $response = $this->call('POST', '/api/webhook/line', [], [], [], [], json_encode(['events' => []]));

        $response->assertStatus(401);
        Queue::assertNotPushed(LineWebhookJob::class);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        Queue::fake();

        $body = json_encode(['events' => []]);

        $response = $this->call(
            'POST',
            '/api/webhook/line',
            [],
            [],
            [],
            ['HTTP_X-Line-Signature' => 'invalid-signature'],
            $body
        );

        $response->assertStatus(401);
        Queue::assertNotPushed(LineWebhookJob::class);
    }

    public function test_webhook_with_valid_signature_is_accepted(): void
    {
        Queue::fake();

        $body = (string) json_encode(['events' => []]);
        $signature = base64_encode(hash_hmac('sha256', $body, self::CHANNEL_SECRET, true));

        $response = $this->call(
            'POST',
            '/api/webhook/line',
            [],
            [],
            [],
            ['HTTP_X-Line-Signature' => $signature],
            $body
        );

        $response->assertStatus(200);
        Queue::assertPushed(LineWebhookJob::class);
    }

    public function test_notify_webhook_without_signature_header_is_rejected(): void
    {
        Queue::fake();

        $response = $this->call('POST', '/api/webhook/line/notify', [], [], [], [], json_encode(['events' => []]));

        $response->assertStatus(401);
    }

    public function test_notify_webhook_with_valid_signature_is_accepted(): void
    {
        Queue::fake();

        $body = (string) json_encode(['events' => []]);
        $signature = base64_encode(hash_hmac('sha256', $body, self::CHANNEL_SECRET, true));

        $response = $this->call(
            'POST',
            '/api/webhook/line/notify',
            [],
            [],
            [],
            ['HTTP_X-Line-Signature' => $signature],
            $body
        );

        $response->assertStatus(200);
    }

    public function test_webhook_without_channel_secret_configured_is_rejected(): void
    {
        config(['bot.line.channel_secret' => '']);
        Queue::fake();

        $body = (string) json_encode(['events' => []]);

        $response = $this->call(
            'POST',
            '/api/webhook/line',
            [],
            [],
            [],
            ['HTTP_X-Line-Signature' => 'anything'],
            $body
        );

        $response->assertStatus(401);
        Queue::assertNotPushed(LineWebhookJob::class);
    }
}
