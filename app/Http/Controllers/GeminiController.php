<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Gemini\Services\GeminiService;
use App\Http\Requests\Apis\Gemini\GeminiChatRequest;
use App\Http\Requests\Apis\Gemini\GeminiGenerateRequest;
use App\Http\Resources\Gemini\GeminiResponseResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeminiController extends ApiController
{
    public function generateContent(GeminiGenerateRequest $request, GeminiService $geminiService): JsonResponse
    {
        $validated = $request->validated();

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

        return $this->response()->success(new GeminiResponseResource($geminiService->generate((string) $validated['prompt'], $options)));
    }

    public function streamContent(GeminiGenerateRequest $request, GeminiService $geminiService): StreamedResponse
    {
        $validated = $request->validated();

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

        return response()->stream(function () use ($geminiService, $validated, $options): void {
            $result = $geminiService->generate((string) $validated['prompt'], $options);
            echo (string) data_get($result, 'text', '');
        }, 200, ['Content-Type' => 'text/event-stream']);
    }

    public function chat(GeminiChatRequest $request, GeminiService $geminiService): JsonResponse
    {
        $validated = $request->validated();

        /** @var array<int, array<string, string>> $messages */
        $messages = (array) $validated['messages'];

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

        return $this->response()->success(new GeminiResponseResource($geminiService->chat($messages, $options)));
    }

    public function listModels(GeminiService $geminiService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'models' => $geminiService->models(),
        ]);
    }
}
