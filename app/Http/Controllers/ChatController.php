<?php

namespace App\Http\Controllers;

use App\Models\GutRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    private const SECTORS = ['mkt', 'juridico', 'rh'];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'sector' => ['nullable', 'string', 'in:'.implode(',', self::SECTORS)],
        ]);

        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            return response()->json([
                'error' => 'OpenAI API key is not configured.',
            ], 500);
        }

        $systemPrompt = trim((string) config('services.openai.system_prompt', ''));
        $sector = '';
        if (isset($data['sector']) && is_string($data['sector']) && $data['sector'] !== '') {
            $sector = strtolower($data['sector']);
        } else {
            $sessionSector = (string) $request->session()->get('chat_sector', '');
            if (in_array($sessionSector, self::SECTORS, true)) {
                $sector = $sessionSector;
            }
        }

        if ($sector !== '') {
            $request->session()->put('chat_sector', $sector);
        }

        $effectivePrompt = $systemPrompt;
        if ($sector !== '') {
            $sectorNote = 'Setor selecionado: '.strtoupper($sector).'. Use exatamente "Setor: '.$sector.'" no formato e responda apenas sobre esse setor.';
            $effectivePrompt = trim($systemPrompt !== '' ? $systemPrompt."\n\n".$sectorNote : $sectorNote);
        }
        $input = [];

        if ($effectivePrompt !== '') {
            $input[] = [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $effectivePrompt,
                    ],
                ],
            ];
        }

        $promptHash = hash('sha256', $effectivePrompt);
        $currentHash = (string) $request->session()->get('chat_prompt_hash', '');
        if ($currentHash !== $promptHash) {
            $request->session()->forget('chat_history');
            $request->session()->put('chat_prompt_hash', $promptHash);
        }

        $history = $request->session()->get('chat_history', []);
        $maxHistory = (int) config('services.openai.max_history', 10);
        $historyItems = [];
        if (is_array($history)) {
            foreach ($history as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $role = $item['role'] ?? null;
                $text = $item['text'] ?? null;
                if (! in_array($role, ['user', 'assistant'], true)) {
                    continue;
                }
                if (! is_string($text) || $text === '') {
                    continue;
                }
                $historyItems[] = [
                    'role' => $role,
                    'text' => $text,
                ];
            }
        }

        if ($maxHistory > 0 && ! empty($historyItems)) {
            $historyItems = array_slice($historyItems, -$maxHistory);
            foreach ($historyItems as $item) {
                $contentType = $item['role'] === 'assistant' ? 'output_text' : 'input_text';
                $input[] = [
                    'role' => $item['role'],
                    'content' => [
                        [
                            'type' => $contentType,
                            'text' => $item['text'],
                        ],
                    ],
                ];
            }
        }

        $input[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => $data['message'],
                ],
            ],
        ];

        $model = config('services.openai.model', 'gpt-5-nano');
        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        if (config('services.openai.store')) {
            $payload['store'] = true;
        }

        $maxOutputTokens = (int) config('services.openai.max_output_tokens', 0);
        $minOutputTokens = str_starts_with($model, 'gpt-5') ? 600 : 300;
        if ($maxOutputTokens <= 0 || $maxOutputTokens < $minOutputTokens) {
            $maxOutputTokens = $minOutputTokens;
        }
        $payload['max_output_tokens'] = $maxOutputTokens;

        $temperature = config('services.openai.temperature');
        if ($temperature !== null && $temperature !== '' && ! str_starts_with($model, 'gpt-5')) {
            $payload['temperature'] = (float) $temperature;
        }

        $reasoningEffort = config('services.openai.reasoning_effort');
        if ($reasoningEffort && str_starts_with($model, 'gpt-5')) {
            $payload['reasoning'] = ['effort' => $reasoningEffort];
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = (int) config('services.openai.timeout', 30);
        $verify = config('services.openai.verify_ssl', true);
        $caBundle = trim((string) config('services.openai.ca_bundle', ''));
        if ($caBundle !== '') {
            $verify = $caBundle;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout($timeout)
                ->withOptions(['verify' => $verify])
                ->post($baseUrl.'/responses', $payload);
        } catch (ConnectionException $e) {
            Log::error('OpenAI connection failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'OpenAI connection failed. Check SSL configuration.',
            ], 502);
        }

        if (! $response->successful()) {
            Log::error('OpenAI API request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'error' => 'OpenAI API request failed.',
            ], 502);
        }

        $body = $response->json();
        if (! is_array($body)) {
            Log::error('OpenAI API response was not JSON.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'error' => 'OpenAI API returned an invalid response.',
            ], 502);
        }

        $text = $this->extractOutputText($body);
        if ($text === '') {
            Log::error('OpenAI API returned empty output.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'error' => 'OpenAI API returned empty output.',
            ], 502);
        }
        $parsed = $this->parseGutResponse($text, $sector !== '' ? $sector : null);

        if ($parsed && $request->user()) {
            try {
                if (Schema::hasTable('gut_requests')) {
                    GutRequest::create([
                        'user_id' => $request->user()->id,
                        'message' => $data['message'],
                        'sector' => $parsed['sector'],
                        'gravity' => $parsed['gravity'],
                        'urgency' => $parsed['urgency'],
                        'trend' => $parsed['trend'],
                        'score' => $parsed['score'],
                        'response_text' => $text,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to persist GUT request.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($maxHistory > 0) {
            $historyItems[] = [
                'role' => 'user',
                'text' => $data['message'],
            ];
            if ($text !== '') {
                $historyItems[] = [
                    'role' => 'assistant',
                    'text' => $text,
                ];
            }
            $historyItems = array_slice($historyItems, -$maxHistory);
            $request->session()->put('chat_history', $historyItems);
        }

        return response()->json([
            'text' => $text,
            'response_id' => $body['id'] ?? null,
            'model' => $body['model'] ?? $payload['model'],
        ]);
    }

    private function extractOutputText(array $body): string
    {
        $output = $body['output'] ?? [];
        $texts = [];

        if (is_array($output)) {
            foreach ($output as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemType = $item['type'] ?? '';
                if ($itemType === 'refusal') {
                    $value = trim((string) ($item['refusal'] ?? $item['text'] ?? ''));
                    if ($value !== '') {
                        $texts[] = $value;
                    }
                    continue;
                }

                if ($itemType !== 'message') {
                    continue;
                }

                $contentItems = $item['content'] ?? [];
                if (is_string($contentItems)) {
                    $value = trim($contentItems);
                    if ($value !== '') {
                        $texts[] = $value;
                    }
                    continue;
                }

                if (! is_array($contentItems)) {
                    continue;
                }

                foreach ($contentItems as $content) {
                    if (! is_array($content)) {
                        continue;
                    }
                    $type = $content['type'] ?? '';
                    if (in_array($type, ['output_text', 'summary_text', 'text', 'refusal'], true)) {
                        $value = trim((string) ($content['text'] ?? $content['refusal'] ?? ''));
                        if ($value !== '') {
                            $texts[] = $value;
                        }
                    }
                }
            }
        }

        if (! empty($texts)) {
            return implode("\n", $texts);
        }

        $fallback = trim((string) ($body['output_text'] ?? ''));
        if ($fallback !== '') {
            return $fallback;
        }

        $fallback = trim((string) ($body['refusal'] ?? ''));
        return $fallback;
    }

    private function parseGutResponse(string $text, ?string $forcedSector = null): ?array
    {
        if ($text === '') {
            return null;
        }

        $normalizedText = Str::ascii($text);

        $sector = null;
        $gravity = null;
        $urgency = null;
        $trend = null;

        if (preg_match('/Setor(?:\s*(?:selecionado|escolhido))?\s*:\s*(mkt|juridico|rh)\b/i', $normalizedText, $match)) {
            $sector = strtolower($match[1]);
        }

        if ($forcedSector && in_array($forcedSector, self::SECTORS, true)) {
            $sector = $forcedSector;
        }

        if (preg_match('/Gravidade(?:\s*\(1-5\))?\s*:\s*(\d)/i', $normalizedText, $match)) {
            $gravity = (int) $match[1];
        }

        if (preg_match('/Urgencia(?:\s*\(1-5\))?\s*:\s*(\d)/i', $normalizedText, $match)) {
            $urgency = (int) $match[1];
        }

        if (preg_match('/Tendencia(?:\s*\(1-5\))?\s*:\s*(\d)/i', $normalizedText, $match)) {
            $trend = (int) $match[1];
        }

        if (! $sector || ! $gravity || ! $urgency || ! $trend) {
            return null;
        }

        if ($gravity < 1 || $gravity > 5 || $urgency < 1 || $urgency > 5 || $trend < 1 || $trend > 5) {
            return null;
        }

        return [
            'sector' => $sector,
            'gravity' => $gravity,
            'urgency' => $urgency,
            'trend' => $trend,
            'score' => $gravity * $urgency * $trend,
        ];
    }
}
