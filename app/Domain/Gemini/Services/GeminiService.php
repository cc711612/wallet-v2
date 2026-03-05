<?php

declare(strict_types=1);

namespace App\Domain\Gemini\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private string $apiKey;

    private string $baseUrl;

    private string $apiVersion;

    private string $defaultModel;

    /**
     * 初始化 Gemini API 設定。
     *
     * @return void
     */
    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $this->apiVersion = (string) config('services.gemini.api_version', 'v1beta');
        $this->defaultModel = (string) config('services.gemini.default_model', 'gemini-2.0-flash');
    }

    /**
     * 文字生成。
     *
     * @param  string  $prompt
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generate(string $prompt, array $options = []): array
    {
        $model = (string) ($options['model'] ?? $this->defaultModel);
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
        ];

        if (isset($options['generationConfig']) && is_array($options['generationConfig'])) {
            $payload['generationConfig'] = $options['generationConfig'];
        }

        return $this->requestGemini('POST', "/{$this->apiVersion}/models/{$model}:generateContent", $payload);
    }

    /**
     * 多輪對話生成。
     *
     * @param  array<int, array<string, string>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $options = []): array
    {
        $model = (string) ($options['model'] ?? $this->defaultModel);

        $contents = array_map(static fn (array $message): array => [
            'role' => (string) ($message['role'] ?? 'user'),
            'parts' => [['text' => (string) ($message['content'] ?? '')]],
        ], $messages);

        $payload = [
            'contents' => $contents,
        ];

        if (isset($options['generationConfig']) && is_array($options['generationConfig'])) {
            $payload['generationConfig'] = $options['generationConfig'];
        }

        return $this->requestGemini('POST', "/{$this->apiVersion}/models/{$model}:generateContent", $payload);
    }

    /**
     * 取得可用模型列表（含快取）。
     *
     * @return array<string, mixed>
     */
    public function listModels(): array
    {
        return Cache::remember('gemini_models_response', now()->addMinutes(30), function (): array {
            return $this->requestGemini('GET', "/{$this->apiVersion}/models");
        });
    }

    /**
     * 統一 Gemini HTTP 呼叫。
     *
     * @param  string  $method
     * @param  string  $path
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestGemini(string $method, string $path, array $payload = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured');
        }

        $url = $this->baseUrl.$path;
        $request = Http::timeout(30)->acceptJson();
        $response = $method === 'GET'
            ? $request->get($url, ['key' => $this->apiKey])
            : $request->post($url.'?key='.$this->apiKey, $payload);

        if (! $response->successful()) {
            $message = (string) data_get($response->json(), 'error.message', 'Gemini API request failed');

            throw new RuntimeException($message);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Gemini API returned invalid response');
        }

        return $json;
    }
}
