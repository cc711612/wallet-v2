<?php

declare(strict_types=1);

namespace App\Domain\Gemini\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiService
{
    private string $apiKey;

    private string $baseUrl;

    private string $apiVersion;

    private string $defaultModel;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $this->apiVersion = (string) config('services.gemini.api_version', 'v1beta');
        $this->defaultModel = (string) config('services.gemini.default_model', 'gemini-2.0-flash');
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(string $prompt, array $options = []): array
    {
        try {
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

            $response = $this->requestGemini('POST', "/{$this->apiVersion}/models/{$model}:generateContent", $payload);
            $text = (string) data_get($response, 'candidates.0.content.parts.0.text', '');

            return [
                'success' => true,
                'text' => $text,
                'raw_response' => $response,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'text' => '',
                'raw_response' => ['error' => $exception->getMessage()],
            ];
        }
    }

    /**
     * @param  array<int, array<string, string>>  $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $options = []): array
    {
        try {
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

            $response = $this->requestGemini('POST', "/{$this->apiVersion}/models/{$model}:generateContent", $payload);
            $text = (string) data_get($response, 'candidates.0.content.parts.0.text', '');

            return [
                'success' => true,
                'text' => $text,
                'raw_response' => $response,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'text' => '',
                'raw_response' => ['error' => $exception->getMessage()],
            ];
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function models(): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        return Cache::remember('gemini_models', now()->addMinutes(30), function (): array {
            $response = $this->requestGemini('GET', "/{$this->apiVersion}/models");
            $models = data_get($response, 'models', []);

            return is_array($models) ? $models : [];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestGemini(string $method, string $path, array $payload = []): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        $url = $this->baseUrl.$path;
        $request = Http::timeout(30)->acceptJson();
        $response = $method === 'GET'
            ? $request->get($url, ['key' => $this->apiKey])
            : $request->post($url.'?key='.$this->apiKey, $payload);

        if (! $response->successful()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
