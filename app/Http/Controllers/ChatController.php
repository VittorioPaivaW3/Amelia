<?php

namespace App\Http\Controllers;

use App\Models\GutRequest;
use App\Models\GutRequestAttachment;
use App\Models\ChatTokenUsage;
use App\Models\PolicyDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChatController extends Controller
{
    private const SECTORS = ['mkt', 'juridico', 'rh'];
    private const CHAT_MODES = ['gut', 'policy'];
    private const POLICY_DEFAULT_SECTOR = 'rh';

    public function show(Request $request): View
    {
        $editRequest = null;
        $prefillMessages = [];

        $requestId = (int) $request->query('request_id', 0);
        if ($requestId > 0 && $request->user()) {
            $editRequest = GutRequest::query()
                ->whereKey($requestId)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $editRequest) {
                abort(404);
            }

            if (! in_array($editRequest->status ?? '', ['novo', ''], true)) {
                abort(403, 'Solicitacao nao pode ser editada.');
            }

            $sector = (string) ($editRequest->sector ?? '');
            if ($sector !== '') {
                $request->session()->put('chat_sector', $sector);
            }

            if (($editRequest->message ?? '') !== '') {
                $prefillMessages[] = [
                    'role' => 'user',
                    'text' => $editRequest->message,
                ];
            }
            if (($editRequest->response_text ?? '') !== '') {
                $prefillMessages[] = [
                    'role' => 'assistant',
                    'text' => $editRequest->response_text,
                ];
            }

            if (! empty($prefillMessages)) {
                $request->session()->put('chat_history', $prefillMessages);
            }

            $effectivePrompt = $this->buildEffectivePrompt($sector);
            $request->session()->put('chat_prompt_hash', hash('sha256', $effectivePrompt));
        }

        return view('chat', [
            'editRequestId' => $editRequest?->id,
            'editSector' => $editRequest?->sector,
            'prefillMessages' => $prefillMessages,
        ]);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'sector' => ['nullable', 'string', 'in:'.implode(',', self::SECTORS)],
            'mode' => ['nullable', 'string', 'in:'.implode(',', self::CHAT_MODES)],
            'message_id' => ['nullable', 'string', 'max:36'],
            'request_id' => ['nullable', 'integer', 'min:1'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ]);

        $rawMessage = (string) $data['message'];
        $effectiveMessage = $rawMessage;
        $historyUserText = $rawMessage;
        $pendingMismatch = $this->getPendingSectorMismatch($request);
        $usingPending = false;
        $skipMismatchCheck = false;
        $useCachedResponse = false;
        $cachedResponse = '';
        $responseAction = '';
        $responseRecommended = '';
        $responseOriginal = '';
        if ($pendingMismatch && $this->isSectorOverrideConfirmation($rawMessage)) {
            $pendingMessage = (string) ($pendingMismatch['message'] ?? '');
            if ($pendingMessage !== '') {
                $effectiveMessage = $pendingMessage;
                $historyUserText = $pendingMessage;
                $usingPending = true;
                $skipMismatchCheck = true;
                $cachedResponse = (string) ($pendingMismatch['response'] ?? '');
                $useCachedResponse = $cachedResponse !== '';
                $pendingSector = (string) ($pendingMismatch['sector'] ?? '');
                if ($pendingSector !== '') {
                    $data['sector'] = $pendingSector;
                }
            }
            $this->clearPendingSectorMismatch($request);
        } elseif ($pendingMismatch) {
            $this->clearPendingSectorMismatch($request);
        }

        $editRequest = null;
        $requestId = (int) ($data['request_id'] ?? 0);
        if ($requestId > 0 && $request->user()) {
            $editRequest = GutRequest::query()
                ->whereKey($requestId)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $editRequest) {
                return response()->json([
                    'error' => 'Solicitacao nao encontrada.',
                ], 404);
            }

            if (! in_array($editRequest->status ?? '', ['novo', ''], true)) {
                return response()->json([
                    'error' => 'Solicitacao nao pode ser editada.',
                ], 403);
            }
        }

        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            return response()->json([
                'error' => 'Assistente ainda nao configurado. Fale com o admin para configurar a API.',
            ], 500);
        }

        $sector = '';
        if (isset($data['sector']) && is_string($data['sector']) && $data['sector'] !== '') {
            $sector = strtolower($data['sector']);
        } else {
            $sessionSector = (string) $request->session()->get('chat_sector', '');
            if (in_array($sessionSector, self::SECTORS, true)) {
                $sector = $sessionSector;
            }
        }

        if ($editRequest && $sector === '') {
            $sector = (string) ($editRequest->sector ?? '');
        }

        if ($sector !== '') {
            $request->session()->put('chat_sector', $sector);
        }

        $mode = (string) ($data['mode'] ?? '');
        if (! in_array($mode, self::CHAT_MODES, true)) {
            $mode = '';
        }
        if ($editRequest || $usingPending) {
            $mode = 'gut';
        } elseif ($mode === '') {
            $mode = $this->detectChatMode($request, $effectiveMessage, $sector);
        }

        $policyDocument = null;
        if ($mode === 'policy') {
            if ($sector === '') {
                $sector = self::POLICY_DEFAULT_SECTOR;
            }
            if (! in_array($sector, self::SECTORS, true)) {
                $sector = self::POLICY_DEFAULT_SECTOR;
            }
            $policyDocument = PolicyDocument::query()
                ->where('sector', $sector)
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->first();
            if (! $policyDocument) {
                if (! $this->isPolicyQuestion($effectiveMessage)) {
                    $mode = 'gut';
                } else {
                    $sectorLabel = strtoupper((string) $sector);
                    $request->session()->put('chat_last_mode', 'policy');

                    return response()->json([
                        'text' => "Nao existe politica ativa para o setor {$sectorLabel}. Peça ao admin para enviar o PDF em Politicas ou selecione outro setor para tirar duvidas.",
                        'action' => 'policy_missing',
                        'sector' => $sector,
                    ]);
                }
            }
        }

        $request->session()->put('chat_last_mode', $mode);

        $sessionSectorKey = $this->sessionKey($mode, 'chat_sector');
        $historyKey = $this->sessionKey($mode, 'chat_history');
        $promptKey = $this->sessionKey($mode, 'chat_prompt_hash');
        $conversationKey = $this->sessionKey($mode, 'chat_conversation_id');

        if ($sector !== '') {
            $request->session()->put($sessionSectorKey, $sector);
        }

        $input = [];
        if ($mode === 'policy' && $policyDocument) {
            $effectivePrompt = $this->buildPolicySystemPrompt($sector);
        } else {
            $effectivePrompt = $this->buildEffectivePrompt($sector);
        }

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

        if ($mode === 'policy' && $policyDocument) {
            $policyContext = $this->selectPolicyContext($policyDocument->text_content, $effectiveMessage);
            if ($policyContext !== '') {
                $sectorLabel = strtoupper((string) $sector);
                $input[] = [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "Trechos das politicas do setor {$sectorLabel}:\n".$policyContext,
                        ],
                    ],
                ];
            }
        }

        $promptHash = $mode === 'policy' && $policyDocument
            ? hash('sha256', $effectivePrompt.'|'.$policyDocument->id)
            : hash('sha256', $effectivePrompt);
        $currentHash = (string) $request->session()->get($promptKey, '');
        if ($currentHash !== $promptHash) {
            $request->session()->forget($historyKey);
            $request->session()->forget($conversationKey);
            $request->session()->put($promptKey, $promptHash);
        }
        $conversationId = (string) $request->session()->get($conversationKey, '');
        if ($conversationId === '') {
            $conversationId = (string) Str::uuid();
            $request->session()->put($conversationKey, $conversationId);
        }
        $messageId = trim((string) ($data['message_id'] ?? ''));
        if ($messageId === '') {
            $messageId = (string) Str::uuid();
        }

        $history = $request->session()->get($historyKey, []);
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
                    'text' => $effectiveMessage,
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

        $tokenUsage = ['input' => 0, 'output' => 0, 'total' => 0];
        $body = [];
        if ($useCachedResponse) {
            $text = $cachedResponse;
        } else {
            try {
                $response = $this->requestOpenAi($apiKey, $baseUrl, $payload, $timeout, $verify);
            } catch (ConnectionException $e) {
                Log::error('OpenAI connection failed.', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Nao foi possivel responder agora. Tente novamente em alguns minutos. Se persistir, recarregue a pagina ou fale com o admin.',
                ], 502);
            }

            if (! $response->successful()) {
                Log::error('OpenAI API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'Nao foi possivel responder agora. Tente novamente em alguns minutos. Se persistir, recarregue a pagina ou fale com o admin.',
                ], 502);
            }

            $body = $response->json();
            if (! is_array($body)) {
                Log::error('OpenAI API response was not JSON.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'Nao foi possivel responder agora. Tente novamente em alguns minutos. Se persistir, recarregue a pagina ou fale com o admin.',
                ], 502);
            }

            $tokenUsage = $this->mergeTokenUsage($tokenUsage, $this->extractTokenUsage($body));
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
                            'error' => 'Nao foi possivel responder agora. Tente novamente em alguns minutos. Se persistir, recarregue a pagina ou fale com o admin.',
                        ], 502);
                    }

                    if (! $response->successful()) {
                        Log::error('OpenAI API request failed.', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return response()->json([
                            'error' => 'Nao foi possivel responder agora. Tente novamente em alguns minutos. Se persistir, recarregue a pagina ou fale com o admin.',
                        ], 502);
                    }

                    $body = $response->json();
                    if (! is_array($body)) {
                        Log::error('OpenAI API response was not JSON.', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return response()->json([
                            'error' => 'Nao foi possivel responder agora. Tente novamente em alguns minutos. Se persistir, recarregue a pagina ou fale com o admin.',
                        ], 502);
                    }

                    $tokenUsage = $this->mergeTokenUsage($tokenUsage, $this->extractTokenUsage($body));
                    $text = $this->extractOutputText($body);
                }
            }
            if ($text === '') {
                Log::error('OpenAI API returned empty output.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'Nao foi possivel responder agora. Tente novamente em alguns minutos. Se persistir, recarregue a pagina ou fale com o admin.',
                ], 502);
            }
        }
        $rawText = $text;
        $recommendedSector = $this->parseRecommendedSector($rawText);
        if ($recommendedSector === '') {
            $recommendedSector = $this->parseSectorFromResponse($rawText);
        }
        $inferredSector = $this->inferSectorFromMessage($effectiveMessage, $sector);
        if ($inferredSector !== '') {
            $recommendedSector = $inferredSector;
        }
        $skipPersistence = false;

        if ($mode === 'gut' && ! $editRequest && ! $skipMismatchCheck && $sector !== '' && $recommendedSector !== '' && $recommendedSector !== $sector) {
            $this->storePendingSectorMismatch($request, [
                'message' => $effectiveMessage,
                'sector' => $sector,
                'recommended' => $recommendedSector,
                'response' => $rawText,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
            ]);
            $text = $this->buildSectorMismatchWarning($sector, $recommendedSector);
            $responseAction = 'sector_mismatch';
            $responseRecommended = $recommendedSector;
            $responseOriginal = $effectiveMessage;
            $skipPersistence = true;
        }

        $text = $this->stripRecommendedSectorLine($text);
        if ($mode === 'gut' && $sector !== '' && ! $skipPersistence) {
            $text = $this->forceSectorLine($text, $sector);
        }

        $parsed = null;
        if ($mode === 'gut' && ! $skipPersistence) {
            $parsed = $this->parseGutResponse($rawText, $sector !== '' ? $sector : null);
        }

        $gutRequestId = $editRequest?->id;
        if ($parsed && $request->user()) {
            try {
                if (Schema::hasTable('gut_requests')) {
                    $title = $this->normalizeTitle($parsed['title'] ?? null);
                    if (! $title) {
                        $title = $this->makeTitleFromMessage($effectiveMessage);
                    }
                    $summary = $this->normalizeSummary($parsed['summary'] ?? null);
                    if ($editRequest) {
                        $originalMessage = $editRequest->original_message;
                        if ($originalMessage === null || $originalMessage === '') {
                            $originalMessage = $editRequest->message;
                        }
                        $originalResponse = $editRequest->original_response_text;
                        if ($originalResponse === null || $originalResponse === '') {
                            $originalResponse = $editRequest->response_text;
                        }

                        if (! $title) {
                            $title = $editRequest->title ?: $this->makeTitle($parsed['sector'] ?? null, $editRequest->id);
                        }
                        if (! $summary) {
                            $summary = $editRequest->summary;
                        }
                        $editRequest->update([
                            'title' => $title,
                            'summary' => $summary,
                            'message' => $effectiveMessage,
                            'original_message' => $originalMessage,
                            'sector' => $parsed['sector'],
                            'gravity' => $parsed['gravity'],
                            'urgency' => $parsed['urgency'],
                            'trend' => $parsed['trend'],
                            'score' => $parsed['score'],
                            'response_text' => $text,
                            'original_response_text' => $originalResponse,
                        ]);
                        $gutRequestId = $editRequest->id;
                        $this->storeAttachments($request, $conversationId, $messageId, $editRequest->id);
                    } else {
                        $gutRequest = GutRequest::create([
                            'user_id' => $request->user()->id,
                            'title' => $title,
                            'summary' => $summary,
                            'message' => $effectiveMessage,
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
                        $gutRequestId = $gutRequest->id;
                        $this->storeAttachments($request, $conversationId, $messageId, $gutRequest->id);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to persist GUT request.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->storeTokenUsage($request, $tokenUsage, [
            'mode' => $mode,
            'sector' => $sector,
            'model' => $model,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'gut_request_id' => $gutRequestId,
        ]);

        if ($maxHistory > 0) {
            $historyItems[] = [
                'role' => 'user',
                'text' => $historyUserText,
            ];
            if ($text !== '') {
                $historyItems[] = [
                    'role' => 'assistant',
                    'text' => $text,
                ];
            }
            $historyItems = array_slice($historyItems, -$maxHistory);
            $request->session()->put($historyKey, $historyItems);
        }

        return response()->json([
            'text' => $text,
            'conversation_id' => $conversationId,
            'response_id' => $body['id'] ?? null,
            'model' => $body['model'] ?? $payload['model'],
            'action' => $responseAction !== '' ? $responseAction : null,
            'recommended_sector' => $responseRecommended !== '' ? $responseRecommended : null,
            'original_message' => $responseOriginal !== '' ? $responseOriginal : null,
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

    private function extractTokenUsage(array $body): array
    {
        $usage = $body['usage'] ?? [];
        if (! is_array($usage)) {
            return ['input' => 0, 'output' => 0, 'total' => 0];
        }

        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));

        return [
            'input' => max(0, $input),
            'output' => max(0, $output),
            'total' => max(0, $total),
        ];
    }

    private function mergeTokenUsage(array $current, array $add): array
    {
        return [
            'input' => (int) ($current['input'] ?? 0) + (int) ($add['input'] ?? 0),
            'output' => (int) ($current['output'] ?? 0) + (int) ($add['output'] ?? 0),
            'total' => (int) ($current['total'] ?? 0) + (int) ($add['total'] ?? 0),
        ];
    }

    private function calculateTokenCost(string $model, array $usage): array
    {
        $inputRate = (float) config('services.openai.input_cost_per_1k', 0);
        $outputRate = (float) config('services.openai.output_cost_per_1k', 0);
        $useAvg = (bool) config('services.openai.use_avg_cost', false);
        $avgRate = (float) config('services.openai.avg_cost_per_1k', 0);
        if ($inputRate <= 0 && $outputRate <= 0 && $useAvg && $avgRate > 0) {
            $inputRate = $avgRate;
            $outputRate = $avgRate;
        } else {
            if ($inputRate <= 0) {
                $inputRate = $outputRate;
            }
            if ($outputRate <= 0) {
                $outputRate = $inputRate;
            }
        }
        $inputTokens = max(0, (int) ($usage['input'] ?? 0));
        $outputTokens = max(0, (int) ($usage['output'] ?? 0));
        $inputCost = ($inputTokens / 1000) * $inputRate;
        $outputCost = ($outputTokens / 1000) * $outputRate;
        $totalCost = $inputCost + $outputCost;

        return [
            'input' => round($inputCost, 6),
            'output' => round($outputCost, 6),
            'total' => round($totalCost, 6),
        ];
    }

    private function storeTokenUsage(Request $request, array $usage, array $meta = []): void
    {
        $total = (int) ($usage['total'] ?? 0);
        if ($total <= 0) {
            return;
        }
        if (! Schema::hasTable('chat_token_usages')) {
            return;
        }

        $user = $request->user();
        if (! $user) {
            return;
        }

        $mode = (string) ($meta['mode'] ?? '');
        if (! in_array($mode, self::CHAT_MODES, true)) {
            $mode = null;
        }
        $sector = (string) ($meta['sector'] ?? '');
        if (! in_array($sector, self::SECTORS, true)) {
            $sector = null;
        }

        $costs = $this->calculateTokenCost((string) ($meta['model'] ?? ''), $usage);

        ChatTokenUsage::create([
            'user_id' => $user->id,
            'gut_request_id' => $meta['gut_request_id'] ?? null,
            'mode' => $mode,
            'sector' => $sector,
            'model' => $meta['model'] ?? null,
            'conversation_id' => $meta['conversation_id'] ?? null,
            'message_id' => $meta['message_id'] ?? null,
            'input_tokens' => (int) ($usage['input'] ?? 0),
            'output_tokens' => (int) ($usage['output'] ?? 0),
            'total_tokens' => $total,
            'input_cost' => $costs['input'],
            'output_cost' => $costs['output'],
            'total_cost' => $costs['total'],
        ]);
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

    private function detectChatMode(Request $request, string $message, string $sector): string
    {
        $normalized = Str::ascii(mb_strtolower($message, 'UTF-8'));

        $policyHints = [
            'politica', 'politicas', 'regra', 'regras', 'norma', 'normas', 'procedimento',
            'ferias', 'licenca', 'licencas', 'atestado', 'beneficio', 'beneficios', 'folga',
            'ponto', 'horario', 'salario', 'vale', 'reembolso', 'auxilio', 'uniforme',
            'duvida', 'duvidas',
        ];
        $gutHints = [
            'solicitacao', 'solicitar', 'solicito', 'demanda', 'chamado', 'abrir',
            'criar', 'preciso', 'problema', 'erro', 'bug', 'corrigir', 'ajustar',
            'implementar', 'fazer', 'produzir', 'desenvolver', 'alterar', 'urgente',
        ];
        $gutStrongHints = [
            'criar', 'redigir', 'elaborar', 'desenvolver', 'produzir', 'entregavel', 'entregaveis',
            'campanha', 'identidade visual', 'kit', 'evento', 'all hands', 'video', 'manual',
            'organizar', 'planejar', 'prazo', 'entrega', 'alinhamento', 'apresentacao',
        ];

        $hasPolicy = $this->containsAny($normalized, $policyHints);
        $hasGut = $this->containsAny($normalized, $gutHints);
        $hasGutStrong = $this->containsAny($normalized, $gutStrongHints);
        $isPolicyQuestion = $this->isPolicyQuestion($message);

        if ($hasGutStrong) {
            return 'gut';
        }
        if ($hasPolicy && $hasGut) {
            return $isPolicyQuestion ? 'policy' : 'gut';
        }
        if ($hasPolicy && ! $hasGut) {
            return 'policy';
        }
        if ($hasGut && ! $hasPolicy) {
            return 'gut';
        }

        $lastMode = (string) $request->session()->get('chat_last_mode', 'gut');

        return $this->classifyModeWithAi($message, $sector, $lastMode);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isPolicyQuestion(string $message): bool
    {
        $normalized = Str::ascii(mb_strtolower($message, 'UTF-8'));
        $normalized = preg_replace('/[^a-z0-9\s\?]/', ' ', $normalized);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return false;
        }

        $actionVerbs = [
            'criar', 'redigir', 'elaborar', 'desenvolver', 'produzir', 'implementar',
            'ajustar', 'corrigir', 'organizar', 'planejar', 'definir',
        ];
        if ($this->containsAny($normalized, $actionVerbs)) {
            return false;
        }

        $questionHints = [
            'qual', 'quais', 'como', 'quando', 'onde', 'posso', 'pode', 'poderia',
            'tem direito', 'tenho direito', 'existe', 'duvida', 'duvidas', '?',
        ];
        $policyHints = [
            'politica', 'politicas', 'regra', 'regras', 'procedimento', 'norma', 'normas',
            'ferias', 'licenca', 'licencas', 'atestado', 'beneficio', 'beneficios', 'folga',
            'ponto', 'salario', 'vale', 'reembolso', 'auxilio', 'uniforme',
        ];

        $hasQuestion = $this->containsAny($normalized, $questionHints);
        $hasPolicy = $this->containsAny($normalized, $policyHints);

        return $hasQuestion && $hasPolicy;
    }

    private function classifyModeWithAi(string $message, string $sector, string $lastMode): string
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            return $lastMode !== '' ? $lastMode : 'gut';
        }

        $model = trim((string) config('services.openai.model', 'gpt-5-nano'));
        $sectorLabel = $sector !== '' ? strtoupper($sector) : 'NAO INFORMADO';
        $systemPrompt = 'Classifique a mensagem como "gut" (solicitacao/demanda) ou "policy" (duvida sobre politicas internas). Responda apenas com: gut ou policy. Se estiver em duvida, responda gut.';
        $userPrompt = "Setor: {$sectorLabel}\nUltimo modo: {$lastMode}\nMensagem: {$message}";

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => $systemPrompt],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $userPrompt],
                    ],
                ],
            ],
            'max_output_tokens' => 32,
        ];

        if (! str_starts_with($model, 'gpt-5')) {
            $payload['temperature'] = 0;
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
            return $lastMode !== '' ? $lastMode : 'gut';
        }

        if (! $response->successful()) {
            return $lastMode !== '' ? $lastMode : 'gut';
        }

        $body = $response->json();
        if (! is_array($body)) {
            return $lastMode !== '' ? $lastMode : 'gut';
        }

        $text = strtolower(trim($this->extractOutputText($body)));

        if (str_contains($text, 'policy')) {
            return 'policy';
        }
        if (str_contains($text, 'gut')) {
            return 'gut';
        }

        return $lastMode !== '' ? $lastMode : 'gut';
    }

    private function sessionKey(string $mode, string $base): string
    {
        return $mode === 'policy' ? 'policy_'.$base : $base;
    }

    private function buildPolicySystemPrompt(string $sector = ''): string
    {
        $sectorLabel = $sector !== '' ? strtoupper($sector) : 'RH';

        return "Voce e a Amelia, assistente de politicas internas. Responda apenas com base nas politicas do setor {$sectorLabel}. Se a resposta nao estiver no documento, diga que nao encontrou nas politicas. Seja objetiva e nao gere solicitacao GUT.";
    }

    private function selectPolicyContext(string $text, string $question): string
    {
        $chunks = preg_split('/\n{2,}/', $text);
        $chunks = array_map('trim', is_array($chunks) ? $chunks : []);
        $chunks = array_values(array_filter($chunks, fn ($chunk) => $chunk !== ''));

        if (empty($chunks)) {
            return '';
        }

        $keywords = $this->extractPolicyKeywords($question);
        if (empty($keywords)) {
            return $this->limitPolicyContext(array_slice($chunks, 0, 3), 3500);
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $haystack = Str::ascii(mb_strtolower($chunk, 'UTF-8'));
            $score = 0;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($haystack, $keyword)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scored[] = [
                    'score' => $score,
                    'chunk' => $chunk,
                ];
            }
        }

        if (empty($scored)) {
            return $this->limitPolicyContext(array_slice($chunks, 0, 3), 3500);
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $selected = array_column(array_slice($scored, 0, 5), 'chunk');

        return $this->limitPolicyContext($selected, 3500);
    }

    private function extractPolicyKeywords(string $text): array
    {
        $clean = Str::ascii(mb_strtolower($text, 'UTF-8'));
        $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
        $words = preg_split('/\s+/', $clean);
        $words = is_array($words) ? $words : [];

        $stopwords = [
            'a', 'o', 'os', 'as', 'de', 'da', 'do', 'dos', 'das', 'e', 'em', 'para', 'por',
            'com', 'sem', 'um', 'uma', 'uns', 'umas', 'no', 'na', 'nos', 'nas', 'ao', 'aos',
            'que', 'se', 'pra', 'pro', 'sobre', 'entre', 'assim', 'isso', 'isto', 'preciso',
            'precisa', 'gostaria', 'duvida', 'duvidas', 'politica', 'politicas', 'setor',
            'rh', 'mkt', 'juridico', 'qual', 'quando', 'como', 'onde', 'quem', 'porque',
            'pode', 'posso', 'tem', 'tenho', 'ter', 'ser', 'estar', 'esta', 'estas', 'estes',
        ];
        $stopwords = array_flip($stopwords);

        $keywords = [];
        foreach ($words as $word) {
            if ($word === '' || isset($stopwords[$word])) {
                continue;
            }
            if (strlen($word) < 3) {
                continue;
            }
            if (! preg_match('/[a-z]/', $word)) {
                continue;
            }
            if (! in_array($word, $keywords, true)) {
                $keywords[] = $word;
            }
            if (count($keywords) >= 12) {
                break;
            }
        }

        return $keywords;
    }

    private function limitPolicyContext(array $chunks, int $maxChars): string
    {
        $parts = [];
        $total = 0;

        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            $addLength = strlen($chunk) + (empty($parts) ? 0 : 2);
            if ($total + $addLength > $maxChars) {
                $remaining = $maxChars - $total;
                if ($remaining > 80) {
                    $chunk = substr($chunk, 0, $remaining - 3).'...';
                    $parts[] = $chunk;
                }
                break;
            }
            $parts[] = $chunk;
            $total += $addLength;
        }

        return implode("\n\n", $parts);
    }

    private function buildEffectivePrompt(string $sector = ''): string
    {
        $systemPrompt = trim((string) config('services.openai.system_prompt', ''));
        if ($sector === '') {
            return $systemPrompt;
        }

        $sectorNote = 'Setor selecionado: '.strtoupper($sector).'. Use exatamente "Setor: '.$sector.'" no formato e responda apenas sobre esse setor.';
        $mismatchNote = 'Se a solicitacao for claramente de outro setor, inclua uma linha "Setor recomendado: <mkt|juridico|rh>" antes do formato, mas mantenha "Setor: '.$sector.'".';
        $combined = $sectorNote."\n".$mismatchNote;
        return trim($systemPrompt !== '' ? $systemPrompt."\n\n".$combined : $combined);
    }

    private function parseRecommendedSector(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $normalized = Str::ascii($text);
        if (preg_match('/Setor\s+(recomendado|sugerido)\s*[:\-]\s*(mkt|juridico|rh)\b/i', $normalized, $match)) {
            return strtolower($match[2]);
        }

        return '';
    }

    private function parseSectorFromResponse(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $normalized = Str::ascii($text);
        if (preg_match('/Setor(?:\s*(?:selecionado|escolhido))?\s*:\s*(mkt|juridico|rh)\b/i', $normalized, $match)) {
            return strtolower($match[1]);
        }

        return '';
    }

    private function stripRecommendedSectorLine(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (! is_array($lines)) {
            return $text;
        }

        $filtered = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*Setor\s+(recomendado|sugerido)\s*[:\-]/i', $line)) {
                continue;
            }
            $filtered[] = $line;
        }

        return trim(implode("\n", $filtered));
    }

    private function buildSectorMismatchWarning(string $selectedSector, string $recommendedSector): string
    {
        $selectedLabel = strtoupper($selectedSector);
        $recommendedLabel = strtoupper($recommendedSector);

        return "Essa demanda parece ser do setor {$recommendedLabel}, nao do {$selectedLabel}. Use os botoes abaixo para enviar ao setor correto ou continuar mesmo assim. Se tiver anexos, envie novamente.";
    }

    private function inferSectorFromMessage(string $message, string $selectedSector = ''): string
    {
        $normalized = Str::ascii(mb_strtolower($message, 'UTF-8'));
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return '';
        }

        $padded = ' '.$normalized.' ';
        $explicit = [
            'mkt' => [' mkt ', ' marketing ', ' time de mkt ', ' equipe de mkt '],
            'rh' => [' rh ', ' recursos humanos ', ' time de rh ', ' equipe de rh '],
            'juridico' => [' juridico ', ' juridica ', ' juridicas '],
        ];

        foreach ($explicit as $sector => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($padded, $needle)) {
                    return $sector;
                }
            }
        }

        $scores = ['mkt' => 0, 'rh' => 0, 'juridico' => 0];
        $weights = [
            'juridico' => [
                ['contrato', 3], ['clausula', 2], ['aditivo', 2], ['acordo', 2],
                ['lgpd', 3], ['compliance', 2], ['processo', 2], ['audiencia', 2],
                ['notificacao', 2], ['intimacao', 2], ['procuracao', 2],
                ['jurisprudencia', 2], ['tributario', 2], ['fiscal', 2],
                ['responsabilidade', 1], ['norma legal', 3], ['regulacao', 2],
            ],
            'mkt' => [
                ['marketing', 3], ['campanha', 2], ['publicidade', 2], ['midia', 2],
                ['conteudo', 1], ['branding', 2], ['marca', 2], ['identidade visual', 3],
                ['design', 2], ['redes sociais', 2], ['social media', 2], ['trafego', 2],
                ['lead', 2], ['seo', 2], ['landing page', 2], ['site', 1],
                ['email mkt', 2], ['newsletter', 2], ['evento', 1], ['all hands', 1],
                ['comunicacao interna', 1], ['kit', 1], ['video', 1],
            ],
            'rh' => [
                ['recursos humanos', 3], ['folha', 3], ['beneficio', 2], ['beneficios', 2],
                ['recrutamento', 3], ['admissao', 3], ['demissao', 3], ['treinamento', 2],
                ['cultura', 2], ['valores', 2], ['clima', 2], ['desempenho', 2],
                ['onboarding', 2], ['bonificacao', 2], ['bonus', 1], ['avaliacao', 2],
                ['ponto', 2], ['ferias', 2], ['licenca', 2], ['atestado', 2],
                ['salario', 2], ['cargo', 1], ['promocao', 1], ['plano de carreira', 2],
            ],
        ];

        foreach ($weights as $sector => $terms) {
            foreach ($terms as [$term, $weight]) {
                if ($term !== '' && str_contains($normalized, $term)) {
                    $scores[$sector] += $weight;
                }
            }
        }

        $maxScore = max($scores);
        if ($maxScore <= 0) {
            return '';
        }

        $topSectors = array_keys(array_filter($scores, fn ($score) => $score === $maxScore));
        if (count($topSectors) > 1) {
            if ($selectedSector !== '' && in_array($selectedSector, $topSectors, true)) {
                return $selectedSector;
            }
            return '';
        }

        $winner = $topSectors[0] ?? '';
        if ($winner === 'juridico' && $scores['juridico'] < 3) {
            return '';
        }

        return $winner;
    }

    private function forceSectorLine(string $text, string $sector): string
    {
        if ($text === '' || $sector === '') {
            return $text;
        }

        $sector = strtolower($sector);
        if (! in_array($sector, self::SECTORS, true)) {
            return $text;
        }

        $pattern = '/^(\s*(?:Recebido\.)?\s*Setor\s*:\s*)(mkt|juridico|rh)\b/mi';
        return preg_replace($pattern, '$1'.$sector, $text) ?? $text;
    }

    private function getPendingSectorMismatch(Request $request): ?array
    {
        $value = $request->session()->get('chat_sector_mismatch');
        return is_array($value) ? $value : null;
    }

    private function storePendingSectorMismatch(Request $request, array $payload): void
    {
        $request->session()->put('chat_sector_mismatch', $payload);
    }

    private function clearPendingSectorMismatch(Request $request): void
    {
        $request->session()->forget('chat_sector_mismatch');
    }

    private function isSectorOverrideConfirmation(string $message): bool
    {
        $normalized = Str::ascii(mb_strtolower($message, 'UTF-8'));
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return false;
        }

        $phrases = [
            'continuar',
            'continuar mesmo assim',
            'insistir',
            'insistir mesmo assim',
            'manter',
            'manter mesmo assim',
            'quero manter',
            'quero continuar',
            'pode manter',
            'pode continuar',
            'seguir',
            'seguir mesmo assim',
            'prosseguir',
            'prosseguir mesmo assim',
        ];

        foreach ($phrases as $phrase) {
            if ($normalized === $phrase || str_starts_with($normalized, $phrase.' ')) {
                return true;
            }
        }

        if (str_starts_with($normalized, 'sim ')
            && $this->containsAny($normalized, ['continuar', 'manter', 'insistir', 'seguir', 'prosseguir'])) {
            return true;
        }

        return false;
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
