<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Gemini\Services\GeminiService;
use App\Http\Requests\Apis\Gemini\GeminiChatRequest;
use App\Http\Requests\Apis\Gemini\GeminiGenerateRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GeminiController extends ApiController
{
    /**
     * Gemini 文字生成。
     */
    public function generateContent(GeminiGenerateRequest $request, GeminiService $geminiService): JsonResponse
    {
        $validated = $request->validated();

        try {
            $response = $geminiService->generate((string) $validated['prompt'], $this->buildOptions($validated));

            return response()->json([
                'success' => true,
                'text' => $this->extractTextFromResponse($response),
                'raw_response' => $response,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Gemini 串流輸出。
     */
    public function streamContent(GeminiGenerateRequest $request, GeminiService $geminiService): StreamedResponse
    {
        $validated = $request->validated();
        $options = $this->buildOptions($validated);

        return response()->stream(function () use ($geminiService, $validated, $options): void {
            try {
                $response = $geminiService->generate((string) $validated['prompt'], $options);
                echo $this->extractTextFromResponse($response);
            } catch (Throwable $exception) {
                echo 'Error: '.$exception->getMessage();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Gemini 多輪對話。
     */
    public function chat(GeminiChatRequest $request, GeminiService $geminiService): JsonResponse
    {
        $validated = $request->validated();

        /** @var array<int, array<string, string>> $messages */
        $messages = (array) $validated['messages'];

        try {
            $response = $geminiService->chat($messages, $this->buildOptions($validated));

            return response()->json([
                'success' => true,
                'text' => $this->extractTextFromResponse($response),
                'raw_response' => $response,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得可用模型列表。
     */
    public function listModels(GeminiService $geminiService): JsonResponse
    {
        try {
            $response = $geminiService->listModels();

            return response()->json([
                'success' => true,
                'models' => data_get($response, 'models', []),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * 建立 Gemini generation options。
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildOptions(array $validated): array
    {
        $options = [];
        if (isset($validated['temperature']) || isset($validated['max_tokens'])) {
            $options['generationConfig'] = [];
            if (isset($validated['temperature'])) {
                $options['generationConfig']['temperature'] = (float) $validated['temperature'];
            }
            if (isset($validated['max_tokens'])) {
                $options['generationConfig']['max_output_tokens'] = (int) $validated['max_tokens'];
            }
        }

        return $options;
    }

    /**
     * 從 Gemini 回應中抽取文字內容。
     *
     * @param  array<string, mixed>  $response
     */
    private function extractTextFromResponse(array $response): string
    {
        $parts = data_get($response, 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            if (is_array($part)) {
                $text .= (string) ($part['text'] ?? '');
            }
        }

        return $text;
    }
}
