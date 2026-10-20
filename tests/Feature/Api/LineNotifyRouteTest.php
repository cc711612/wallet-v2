<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\SendLineMessage;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P0-8：LINE Notify 死路由移除；/notify 推播轉發只接受共享密鑰。
 */
class LineNotifyRouteTest extends TestCase
{
    public function test_dead_line_notify_routes_are_removed(): void
    {
        foreach (['notifyBind', 'notifyToken', 'notifySendMessage'] as $path) {
            // 原本是 Route::any；移除後 GET 落到 fallback（404），POST 為 405
            $this->getJson('/api/webhook/line/'.$path)->assertNotFound();
            $this->postJson('/api/webhook/line/'.$path, ['message' => 'x'])->assertStatus(405);
        }
    }

    public function test_notify_without_relay_key_is_rejected(): void
    {
        Queue::fake();
        config(['bot.line.notify_relay_key' => 'relay-secret']);

        $this->postJson('/api/webhook/line/notify', ['userIds' => ['U1'], 'message' => 'x'])->assertStatus(401);
        $this->postJson('/api/webhook/line/notify', ['message' => 'x'], ['X-Notify-Relay-Key' => 'wrong'])->assertStatus(401);

        Queue::assertNotPushed(SendLineMessage::class);
    }

    public function test_notify_is_rejected_when_relay_key_not_configured(): void
    {
        Queue::fake();
        config(['bot.line.notify_relay_key' => '']);

        $this->postJson('/api/webhook/line/notify', ['message' => 'x'], ['X-Notify-Relay-Key' => ''])->assertStatus(401);

        Queue::assertNotPushed(SendLineMessage::class);
    }

    public function test_notify_with_valid_relay_key_is_queued(): void
    {
        Queue::fake();
        config(['bot.line.notify_relay_key' => 'relay-secret']);

        $this->postJson('/api/webhook/line/notify', ['message' => 'x'], ['X-Notify-Relay-Key' => 'relay-secret'])->assertOk();

        Queue::assertPushed(SendLineMessage::class);
    }
}
