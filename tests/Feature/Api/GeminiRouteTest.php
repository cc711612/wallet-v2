<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAuthFixtures;
use Tests\TestCase;

/**
 * P0-7：Gemini 端點需登入、有限流，錯誤不回傳原始例外訊息。
 */
class GeminiRouteTest extends TestCase
{
    use BuildsAuthFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'secret-gemini-key']);
    }

    public function test_requires_authentication(): void
    {
        Http::fake();

        $this->postJson('/api/gemini/generate', ['prompt' => 'hi'])->assertStatus(401);
        $this->getJson('/api/gemini/models')->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_authenticated_request_is_throttled(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]])]);
        $jwt = $this->userJwt($this->createUser(1, 'roy'));

        for ($i = 0; $i < 10; $i++) {
            $this->withToken($jwt)->postJson('/api/gemini/generate', ['prompt' => 'hi'])->assertOk();
        }

        $this->withToken($jwt)->postJson('/api/gemini/generate', ['prompt' => 'hi'])->assertStatus(429);
    }

    public function test_error_response_does_not_leak_exception_message(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'upstream detail https://internal?key=secret-gemini-key']], 500)]);
        $jwt = $this->userJwt($this->createUser(1, 'roy'));

        $response = $this->withToken($jwt)->postJson('/api/gemini/generate', ['prompt' => 'hi']);

        $response->assertStatus(500)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('upstream detail', (string) $response->getContent());
        $this->assertStringNotContainsString('secret-gemini-key', (string) $response->getContent());
    }
}
