<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * LINE Notify 相關推播入口全部移除（LINE Notify 已停止服務，/notify 轉發入口長期無人呼叫）。
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

    public function test_notify_relay_route_is_removed(): void
    {
        Queue::fake();

        $this->assertFalse(Route::has('api.webhook.line.notify'));

        // 即使帶上過去的共享密鑰 header，也不再有任何處理，且不會推入任何 job
        $this->postJson('/api/webhook/line/notify', ['message' => 'x'], ['X-Notify-Relay-Key' => 'relay-secret'])
            ->assertStatus(405);
        $this->getJson('/api/webhook/line/notify')->assertNotFound();

        Queue::assertNothingPushed();
    }
}
