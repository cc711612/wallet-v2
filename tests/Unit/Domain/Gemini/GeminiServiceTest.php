<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Gemini;

use App\Domain\Gemini\Exceptions\GeminiTransientException;
use App\Domain\Gemini\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P0-3：Gemini API key 必須放在 x-goog-api-key header，不可出現在 URL。
 */
class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'secret-gemini-key',
            'services.gemini.retry_times' => 3,
            'services.gemini.retry_delay_ms' => 0,
        ]);
        Cache::flush();
    }

    public function test_generate_sends_key_in_header_not_url(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'hi']]]]]])]);

        (new GeminiService)->generate('hello');

        Http::assertSent(static fn (Request $request): bool => ! str_contains($request->url(), 'key=')
            && ! str_contains($request->url(), 'secret-gemini-key')
            && $request->hasHeader('x-goog-api-key', 'secret-gemini-key'));
    }

    public function test_list_models_sends_key_in_header_not_url(): void
    {
        Http::fake(['*' => Http::response(['models' => [['name' => 'gemini-2.0-flash']]])]);

        (new GeminiService)->listModels();

        Http::assertSent(static fn (Request $request): bool => ! str_contains($request->url(), 'key=')
            && $request->hasHeader('x-goog-api-key', 'secret-gemini-key'));
    }

    /** @return array<string, mixed> */
    private function okBody(): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => 'hi']]]]]];
    }

    /** @return array<string, mixed> */
    private function overloadedBody(): array
    {
        return [
            'error' => [
                'code' => 503,
                'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary.',
                'status' => 'UNAVAILABLE',
            ],
        ];
    }

    public function test_generate_retries_on_overload_then_succeeds(): void
    {
        Http::fake(['*' => Http::sequence()->push($this->overloadedBody(), 503)->push($this->okBody())]);

        $result = (new GeminiService)->generate('hello');

        $this->assertSame('hi', $result['candidates'][0]['content']['parts'][0]['text']);
        Http::assertSentCount(2);
    }

    public function test_generate_gives_up_after_retry_times_on_persistent_overload(): void
    {
        Http::fake(['*' => Http::response($this->overloadedBody(), 503)]);

        try {
            (new GeminiService)->generate('hello');
            $this->fail('expected GeminiTransientException');
        } catch (GeminiTransientException $e) {
            $this->assertStringContainsString('high demand', $e->getMessage());
        }

        Http::assertSentCount(3);
    }

    public function test_generate_retries_on_429(): void
    {
        Http::fake(['*' => Http::sequence()->push([], 429)->push($this->okBody())]);

        (new GeminiService)->generate('hello');

        Http::assertSentCount(2);
    }

    public function test_generate_does_not_retry_client_errors(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 400, 'message' => 'API key not valid', 'status' => 'INVALID_ARGUMENT']], 400)]);

        try {
            (new GeminiService)->generate('hello');
            $this->fail('expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(GeminiTransientException::class, $e);
            $this->assertSame('API key not valid', $e->getMessage());
        }

        Http::assertSentCount(1);
    }
}
