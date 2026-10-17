<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Gemini;

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

        config(['services.gemini.api_key' => 'secret-gemini-key']);
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
}
