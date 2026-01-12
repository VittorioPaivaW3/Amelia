<?php

namespace App\Http\Controllers;

use App\Models\GutRequest;
use App\Models\GutRequestAttachment;
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
            'message_id' => ['nullable', 'string', 'max:36'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
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
            $request->session()->forget('chat_conversation_id');
            $request->session()->put('chat_prompt_hash', $promptHash);
        }
        $conversationId = (string) $request->session()->get('chat_conversation_id', '');
        if ($conversationId === '') {
            $conversationId = (string) Str::uuid();
            $request->session()->put('chat_conversation_id', $conversationId);
        }
        $messageId = trim((string) ($data['message_id'] ?? ''));
        if ($messageId === '') {
            $messageId = (string) Str::uuid();
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

        $model = trim((string) config('services.openai.model', 'gpt-5-nano'));
        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        if (config('services.openai.store')) {
            $payload['store'] = true;
        }

        $maxOutputTokens = (int) config('services.openai.max_output_tokens', 0);
        $minOutputTokens = str_starts_with($model, 'gpt-5') ? 1200 : 300;
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
            $response = $this->requestOpenAi($apiKey, $baseUrl, $payload, $timeout, $verify);
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
        if ($text === '' && $this->isMaxOutputTokensIncomplete($body)) {
            $retryMaxOutputTokens = max((int) $payload['max_output_tokens'] * 2, 1200);
            $retryMaxOutputTokens = min($retryMaxOutputTokens, 2400);
            if ($retryMaxOutputTokens > $payload['max_output_tokens']) {
                $payload['max_output_tokens'] = $retryMaxOutputTokens;

                try {
                    $response = $this->requestOpenAi($apiKey, $baseUrl, $payload, $timeout, $verify);
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
            }
        }
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
                    $title = $this->normalizeTitle($parsed['title'] ?? null);
                    if (! $title) {
                        $title = $this->makeTitleFromMessage($data['message']);
                    }
                    $summary = $this->normalizeSummary($parsed['summary'] ?? null);
                    $gutRequest = GutRequest::create([
                        'user_id' => $request->user()->id,
                        'title' => $title,
                        'summary' => $summary,
                        'message' => $data['message'],
                        'sector' => $parsed['sector'],
                        'gravity' => $parsed['gravity'],
                        'urgency' => $parsed['urgency'],
                        'trend' => $parsed['trend'],
                        'score' => $parsed['score'],
                        'response_text' => $text,
                    ]);
                    if (! $title) {
                        $gutRequest->update([
                            'title' => $this->makeTitle($parsed['sector'] ?? null, $gutRequest->id),
                        ]);
                    }
                    $this->storeAttachments($request, $conversationId, $messageId, $gutRequest->id);
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
            'conversation_id' => $conversationId,
            'response_id' => $body['id'] ?? null,
            'model' => $body['model'] ?? $payload['model'],
        ]);
    }

    private function requestOpenAi(string $apiKey, string $baseUrl, array $payload, int $timeout, $verify)
    {
        $response = $this->sendOpenAiRequest($apiKey, $baseUrl, $payload, $timeout, $verify);

        if (! $response->successful() && $this->shouldRetryStatus($response->status())) {
            usleep(200000);
            $response = $this->sendOpenAiRequest($apiKey, $baseUrl, $payload, $timeout, $verify);
        }

        return $response;
    }

    private function sendOpenAiRequest(string $apiKey, string $baseUrl, array $payload, int $timeout, $verify)
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions(['verify' => $verify])
            ->post($baseUrl.'/responses', $payload);
    }

    private function shouldRetryStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
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

    private function isMaxOutputTokensIncomplete(array $body): bool
    {
        return ($body['status'] ?? '') === 'incomplete'
            && (($body['incomplete_details']['reason'] ?? '') === 'max_output_tokens');
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
        $title = null;
        $summary = null;

        if (preg_match('/Setor(?:\s*(?:selecionado|escolhido))?\s*:\s*(mkt|juridico|rh)\b/i', $normalizedText, $match)) {
            $sector = strtolower($match[1]);
        }

        if (preg_match('/^(?:Titulo|Title)\s*:\s*(.+)$/mi', $text, $match)) {
            $title = trim((string) $match[1]);
        } elseif (preg_match('/^(?:Titulo|Title)\s*:\s*(.+)$/mi', $normalizedText, $match)) {
            $title = trim((string) $match[1]);
        }

        if (preg_match('/^Resumo\s*:\s*(.*?)(?:\n\s*Gravidade\b)/si', $text, $match)) {
            $summary = trim((string) $match[1]);
        } elseif (preg_match('/^Resumo\s*:\s*(.*?)(?:\n\s*Gravidade\b)/si', $normalizedText, $match)) {
            $summary = trim((string) $match[1]);
        } elseif (preg_match('/^Resumo\s*:\s*(.+)$/mi', $text, $match)) {
            $summary = trim((string) $match[1]);
        } elseif (preg_match('/^Resumo\s*:\s*(.+)$/mi', $normalizedText, $match)) {
            $summary = trim((string) $match[1]);
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
            'title' => $title,
            'summary' => $summary,
        ];
    }

    private function storeAttachments(Request $request, string $conversationId, string $messageId, int $gutRequestId): int
    {
        $user = $request->user();
        if (! $user || ! $request->hasFile('attachments')) {
            return 0;
        }

        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $rows = [];
        $now = now();
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $extension = $file->getClientOriginalExtension();
            $filename = (string) Str::uuid();
            if ($extension !== '') {
                $filename .= '.'.$extension;
            }
            $path = $file->storeAs('gut-attachments/'.$conversationId, $filename);

            $rows[] = [
                'gut_request_id' => $gutRequestId,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'user_id' => $user->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => (string) $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        GutRequestAttachment::insert($rows);

        return count($rows);
    }

    private function makeTitle(?string $sector = null, ?int $id = null): string
    {
        $sectorLabel = $sector ? strtoupper($sector) : 'GERAL';

        if ($id) {
            return 'Demanda #'.$id.' - '.$sectorLabel;
        }

        return 'Demanda - '.$sectorLabel;
    }

    private function normalizeTitle(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        $title = Str::ascii($title);
        $title = trim(preg_replace('/\s+/', ' ', $title));
        if ($title === '') {
            return null;
        }

        if (preg_match('/^(demanda|solicitacao|solicitacao|chamado)\b/i', $title)) {
            return null;
        }

        $words = preg_split('/\s+/', $title);
        $words = is_array($words) ? array_values(array_filter($words, fn ($word) => $word !== '')) : [];
        if (count($words) < 2) {
            return null;
        }
        if (count($words) > 6) {
            $title = implode(' ', array_slice($words, 0, 6));
        }

        if (strlen($title) > 80) {
            $title = substr($title, 0, 77).'...';
        }

        return $title;
    }

    private function normalizeSummary(?string $summary): ?string
    {
        if ($summary === null) {
            return null;
        }

        $summary = Str::ascii($summary);
        $summary = str_replace(["\r\n", "\r"], "\n", $summary);
        $lines = array_map('trim', explode("\n", $summary));
        $lines = array_values(array_filter($lines, fn ($line) => $line !== ''));
        if (empty($lines)) {
            return null;
        }
        if (count($lines) > 3) {
            $lines = array_slice($lines, 0, 3);
        }

        $lines = array_map(function ($line) {
            return preg_replace('/\s+/', ' ', $line);
        }, $lines);

        $summary = implode("\n", $lines);
        $summary = trim(preg_replace('/\n{3,}/', "\n\n", $summary));
        if (strlen($summary) > 320) {
            $summary = substr($summary, 0, 317).'...';
        }

        return $summary;
    }

    private function makeTitleFromMessage(string $message): ?string
    {
        $clean = Str::ascii(mb_strtolower($message, 'UTF-8'));
        $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
        $words = preg_split('/\s+/', $clean);
        if (! is_array($words)) {
            return null;
        }

        $stopwords = [
            'a', 'o', 'os', 'as', 'de', 'da', 'do', 'dos', 'das', 'e', 'em', 'para', 'por',
            'com', 'sem', 'um', 'uma', 'uns', 'umas', 'no', 'na', 'nos', 'nas', 'ao', 'aos',
            'que', 'se', 'pra', 'pro', 'sobre', 'entre', 'assim', 'isso', 'isto', 'preciso',
            'precisa', 'gostaria', 'solicito', 'solicitacao', 'demanda', 'ajuda', 'favor',
            'porfavor', 'favor', 'meu', 'minha', 'minhas', 'meus', 'ate', 'ate', 'hoje',
        ];
        $stopwords = array_flip($stopwords);

        $keywords = [];
        foreach ($words as $word) {
            if ($word === '' || isset($stopwords[$word])) {
                continue;
            }
            if (strlen($word) < 4) {
                continue;
            }
            if (! preg_match('/[a-z]/', $word)) {
                continue;
            }
            if (! in_array($word, $keywords, true)) {
                $keywords[] = $word;
            }
            if (count($keywords) >= 4) {
                break;
            }
        }

        if (empty($keywords)) {
            return null;
        }

        $title = implode(' ', array_map('ucfirst', $keywords));
        if (strlen($title) > 80) {
            $title = substr($title, 0, 77).'...';
        }

        return $title;
    }
}
