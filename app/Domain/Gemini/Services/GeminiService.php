<?php

declare(strict_types=1);

namespace App\Domain\Gemini\Services;

use App\Domain\Gemini\Exceptions\GeminiTransientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    private string $apiKey;

    private string $baseUrl;

    private string $apiVersion;

    private string $defaultModel;

    private int $timeout;

    private int $retryTimes;

    private int $retryDelayMs;

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
        $this->timeout = (int) config('services.gemini.timeout', 15);
        $this->retryTimes = (int) config('services.gemini.retry_times', 3);
        $this->retryDelayMs = (int) config('services.gemini.retry_delay_ms', 2000);
    }

    /**
     * 文字生成。
     *
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestGemini(string $method, string $path, array $payload = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured');
        }

        $url = $this->baseUrl.$path;

        return retry($this->retryTimes, function () use ($method, $url, $payload): array {
            // API key 放 header，避免出現在 URL（Telescope、log、例外訊息都會記錄 URL）
            $request = Http::timeout($this->timeout)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey]);
            $response = $method === 'GET'
                ? $request->get($url)
                : $request->post($url, $payload);

            if ($response->status() === 429) {
                Log::warning('GeminiService rate limited, retrying...', [
                    'url' => $url,
                ]);
                throw new GeminiTransientException('gemini_rate_limited');
            }

            if (! $response->successful()) {
                $message = (string) data_get($response->json(), 'error.message', 'Gemini API request failed');

                // 服務過載（503 / UNAVAILABLE / "high demand"）通常幾秒內恢復，值得重試；其他錯誤（400、401、403…）重試沒有意義
                if ($this->isOverloaded($response->status(), $response->json())) {
                    Log::warning('GeminiService overloaded, retrying...', [
                        'url' => $url,
                        'status' => $response->status(),
                        'message' => $message,
                    ]);
                    throw new GeminiTransientException($message);
                }

                throw new RuntimeException($message);
            }

            $json = $response->json();

            if (! is_array($json)) {
                throw new RuntimeException('Gemini API returned invalid response');
            }

            return $json;
        }, $this->retryDelayMs, static fn (\Throwable $e): bool => $e instanceof GeminiTransientException);
    }

    /**
     * 判斷回應是否為「模型過載」這類暫時性錯誤。
     */
    private function isOverloaded(int $status, mixed $json): bool
    {
        if ($status === 503) {
            return true;
        }

        $errorStatus = (string) data_get($json, 'error.status', '');
        $message = strtolower((string) data_get($json, 'error.message', ''));

        return $errorStatus === 'UNAVAILABLE' || str_contains($message, 'high demand');
    }
}
