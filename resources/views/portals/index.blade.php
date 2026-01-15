<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Portal {{ strtoupper($sector) }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ openId: null, rejectId: null }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="admin-filter-row mb-6">
                <div>
                    <div class="admin-filter-title">Filtros dos chamados</div>
                    <div class="admin-filter-sub">Busque por periodo e ID</div>
                </div>
                <form method="GET" action="{{ route('portal.sector', ['sector' => $sector]) }}" class="admin-filter-form">
                    <label class="admin-filter-field">
                        <span>De</span>
                        <input type="date" name="from" value="{{ $filterFrom ?? '' }}" class="admin-filter-input">
                    </label>
                    <label class="admin-filter-field">
                        <span>Ate</span>
                        <input type="date" name="to" value="{{ $filterTo ?? '' }}" class="admin-filter-input">
                    </label>
                    <label class="admin-filter-field">
                        <span>ID</span>
                        <input type="text" name="request_id" value="{{ $filterRequestId ?? '' }}" class="admin-filter-input" placeholder="#123">
                    </label>
                    <label class="admin-filter-field">
                        <span>Setor</span>
                        <select class="admin-filter-input" disabled>
                            <option value="{{ $sector }}">{{ strtoupper($sector) }}</option>
                        </select>
                    </label>
                    <button type="submit" class="admin-filter-button">Aplicar</button>
                    <a href="{{ route('portal.sector', ['sector' => $sector]) }}" class="admin-filter-button">Limpar</a>
                </form>
            </div>

            @if ($requests->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        Nenhuma solicitacao neste setor ainda.
                    </div>
                </div>
            @else
                <div class="request-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                    @foreach ($requests as $requestItem)
                        @php
                            $status = $requestItem->status ?? '';
                            $statusLabel = $status !== '' ? str_replace('_', ' ', $status) : 'sem status';
                            $sectorValue = $requestItem->sector ?? $sector ?? '';
                            $palette = [
                                'juridico' => ['accent' => '#ef4444', 'deep' => '#b91c1c'],
                                'mkt' => ['accent' => '#3b82f6', 'deep' => '#1d4ed8'],
                                'rh' => ['accent' => '#f59e0b', 'deep' => '#b45309'],
                                'default' => ['accent' => '#6b7280', 'deep' => '#374151'],
                            ];
                            $isRejected = in_array($status, ['recusado', 'cancelado'], true);
                            $isCanceled = $status === 'cancelado';
                            $isAccepted = $status === 'em_andamento';
                            $colors = $palette[$sectorValue] ?? $palette['default'];
                            $reasonLabel = $isCanceled ? 'Motivo do cancelamento' : 'Justificativa da recusa';
                            $cardClass = 'request-card';
                            if ($isRejected) {
                                $cardClass .= ' request-card--rejected';
                            } elseif ($isAccepted) {
                                $cardClass .= ' request-card--accepted';
                            }
                            $cardStyle = $isRejected
                                ? 'background: #e5e7eb;--sector-accent: #111827;--sector-accent-deep: #111827;'
                                : 'background: '.$colors['accent'].';--sector-accent: '.$colors['accent'].';--sector-accent-deep: '.$colors['deep'].';';
                            $canAct = $status === 'novo' || $status === '';
                        @endphp
                        <div class="{{ $cardClass }}" style="{{ $cardStyle }}">
                            <div class="request-card__hero">
                                @if ($canAct)
                                    <button type="button"
                                        class="request-card__reject"
                                        title="Recusar"
                                        @click="rejectId = {{ $requestItem->id }}">
                                        X
                                    </button>
                                @endif
                                <div class="request-card__gut">
                                    <span class="request-card__gut-label">GUT</span>
                                    <span class="request-card__gut-score">{{ $requestItem->score }}</span>
                                </div>
                            </div>
                            <div class="request-card__body">
                                <div class="request-card__meta">
                                    #{{ $requestItem->id }} - {{ $requestItem->created_at?->format('d/m/Y H:i') }}
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    @if ($status !== 'em_andamento')
                                        <span @class([
                                            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide',
                                            'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700' => $status === 'novo' || $status === '',
                                            'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-900' => $status === 'concluido',
                                            'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-200 dark:border-red-900' => in_array($status, ['recusado', 'cancelado'], true),
                                        ])>
                                            {{ $statusLabel }}
                                        </span>
                                    @endif
                                    @if ($isAccepted)
                                        <span class="request-card__accepted">
                                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                                <path d="M7.8 13.6 4.9 10.7a1 1 0 1 1 1.4-1.4l1.5 1.5 5-5a1 1 0 1 1 1.4 1.4l-6.4 6.4a1 1 0 0 1-1.4 0z"></path>
                                            </svg>
                                            Aceita
                                        </span>
                                    @endif
                                </div>
                                <div class="request-card__message request-card__summary whitespace-pre-line">
                                    {{ \Illuminate\Support\Str::limit($requestItem->summary ?: $requestItem->message, 320) }}
                                </div>
                                <div class="request-card__sub">
                                    Solicitante: {{ $requestItem->user?->name ?? 'Usuario removido' }}
                                </div>
                            </div>
                            <div class="request-card__actions">
                                <button type="button"
                                    class="request-card__details"
                                    @click="openId = {{ $requestItem->id }}">
                                    Detalhes
                                </button>
                                @if ($canAct)
                                    <form method="POST" action="{{ route('gut-requests.update', $requestItem) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="em_andamento">
                                        <input type="hidden" name="rejection_reason" value="">
                                        <button type="submit"
                                            class="request-card__accept"
                                            aria-label="Aceitar">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <polygon points="8,5 19,12 8,19"></polygon>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div x-show="openId === {{ $requestItem->id }}"
                             x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center">
                            <div class="absolute inset-0 bg-black/50" @click="openId = null"></div>
                            <div class="relative w-[calc(100%-2rem)] sm:w-full max-w-3xl max-h-[85vh] overflow-y-auto no-scrollbar">
                                <div class="request-modal" style="--modal-accent: {{ $colors['accent'] ?? '#6b7280' }};">
                                    <div class="request-modal__header">
                                        <div>
                                            <div class="request-modal__eyebrow">Detalhes do chamado</div>
                                            <h3 class="request-modal__title">Solicitacao #{{ $requestItem->id }}</h3>
                                            <div class="request-modal__meta">
                                                <span class="request-modal__chip">{{ strtoupper($sectorValue !== '' ? $sectorValue : 'setor') }}</span>
                                                <span class="request-modal__chip">{{ $requestItem->created_at?->format('d/m/Y H:i') }}</span>
                                                <span @class([
                                                    'request-modal__status',
                                                    'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700' => $status === 'novo' || $status === '',
                                                    'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-900' => $status === 'em_andamento',
                                                    'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-900' => $status === 'concluido',
                                                    'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-200 dark:border-red-900' => in_array($status, ['recusado', 'cancelado'], true),
                                                ])>
                                                    {{ $statusLabel }}
                                                </span>
                                            </div>
                                        </div>
                                        <button type="button" class="request-modal__close" @click="openId = null">
                                            Fechar
                                        </button>
                                    </div>

                                    @php
                                        $requestUser = $requestItem->user;
                                        $photoPath = $requestUser?->profile_photo_path;
                                        $photoUrl = $photoPath ? asset('storage/'.$photoPath) : null;
                                        $initials = $requestUser
                                            ? collect(explode(' ', trim($requestUser->name)))
                                                ->filter()
                                                ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                                ->take(2)
                                                ->implode('')
                                            : '';
                                    @endphp
                                    <div class="request-modal__body">
                                        <div class="request-modal__grid">
                                            <div class="request-modal__card">
                                                <div class="request-modal__label">Solicitante</div>
                                                <div class="mt-3 flex items-center gap-3">
                                                    @if ($photoUrl)
                                                        <img src="{{ $photoUrl }}" alt="{{ $requestUser?->name ?? 'Usuario' }}" class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700">
                                                    @else
                                                        <div class="h-10 w-10 rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200 flex items-center justify-center text-xs font-bold">
                                                            {{ $initials }}
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="request-modal__value">{{ $requestUser?->name ?? 'Usuario' }}</div>
                                                        @if ($requestUser?->email)
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $requestUser->email }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="request-modal__card">
                                                <div class="request-modal__label">GUT</div>
                                                <div class="request-modal__value">
                                                    G {{ $requestItem->gravity }}, U {{ $requestItem->urgency }}, T {{ $requestItem->trend }} ({{ $requestItem->score }})
                                                </div>
                                            </div>
                                        </div>

                                        @php
                                            $hasSecondConversation = ($requestItem->original_message ?? '') !== ''
                                                || ($requestItem->original_response_text ?? '') !== '';
                                        @endphp

                                        @if ($hasSecondConversation)
                                            <div class="request-modal__section" x-data="{ convoTab: 'current' }">
                                                <div class="request-modal__label">Conversa com o chat</div>
                                                <div class="request-modal__tabs">
                                                    <button type="button"
                                                        class="request-modal__tab"
                                                        :class="convoTab === 'original' ? 'is-active' : ''"
                                                        @click="convoTab = 'original'">
                                                        Conversa 1
                                                    </button>
                                                    <button type="button"
                                                        class="request-modal__tab"
                                                        :class="convoTab === 'current' ? 'is-active' : ''"
                                                        @click="convoTab = 'current'">
                                                        Conversa 2
                                                    </button>
                                                </div>

                                                <div class="request-modal__tab-panel" x-show="convoTab === 'original'" x-cloak>
                                                    <div class="request-modal__label">Mensagem</div>
                                                    <div class="request-modal__text whitespace-pre-line">
                                                        {{ $requestItem->original_message ?? 'Sem mensagem registrada.' }}
                                                    </div>
                                                    <div class="request-modal__label">Resposta do chat</div>
                                                    <div class="request-modal__text whitespace-pre-line">
                                                        {{ $requestItem->original_response_text ?? 'Sem resposta registrada.' }}
                                                    </div>
                                                </div>

                                                <div class="request-modal__tab-panel" x-show="convoTab === 'current'" x-cloak>
                                                    <div class="request-modal__label">Mensagem</div>
                                                    <div class="request-modal__text whitespace-pre-line">
                                                        {{ $requestItem->message }}
                                                    </div>
                                                    <div class="request-modal__label">Resposta do chat</div>
                                                    <div class="request-modal__text whitespace-pre-line">
                                                        {{ $requestItem->response_text ?? 'Sem resposta registrada.' }}
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="request-modal__section">
                                                <div class="request-modal__label">Mensagem</div>
                                                <div class="request-modal__text whitespace-pre-line">
                                                    {{ $requestItem->message }}
                                                </div>
                                            </div>

                                            <div class="request-modal__section">
                                                <div class="request-modal__label">Resposta do chat</div>
                                                <div class="request-modal__text whitespace-pre-line">
                                                    {{ $requestItem->response_text ?? 'Sem resposta registrada.' }}
                                                </div>
                                            </div>
                                        @endif

                                        @php
                                            $attachments = $requestItem->attachments ?? collect();
                                        @endphp
                                        @if ($attachments->isNotEmpty())
                                            <div class="request-modal__section">
                                                <div class="request-modal__label">Anexos</div>
                                                <div class="request-modal__attachments">
                                                    @foreach ($attachments as $attachment)
                                                        @php
                                                            $sizeKb = (int) ceil((int) ($attachment->size ?? 0) / 1024);
                                                            $sizeLabel = $sizeKb >= 1024
                                                                ? number_format($sizeKb / 1024, 1, '.', '').' MB'
                                                                : $sizeKb.' KB';
                                                        @endphp
                                                        <a href="{{ route('attachments.download', $attachment) }}" class="request-modal__attachment">
                                                            <span class="request-modal__attachment-name">{{ $attachment->original_name }}</span>
                                                            <span class="request-modal__attachment-meta">{{ $sizeLabel }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if (($requestItem->rejection_reason ?? '') !== '')
                                            <div class="request-modal__section">
                                                <div class="request-modal__label request-modal__label--danger">{{ $reasonLabel }}</div>
                                                <div class="request-modal__text whitespace-pre-line">
                                                    {{ $requestItem->rejection_reason }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div x-show="rejectId === {{ $requestItem->id }}"
                             x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center">
                            <div class="absolute inset-0 bg-black/50" @click="rejectId = null"></div>
                            <div class="relative bg-white dark:bg-gray-900 w-[calc(100%-2rem)] sm:w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-lg shadow-lg p-6">
                                <div class="flex items-start justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        Recusar solicitacao #{{ $requestItem->id }}
                                    </h3>
                                    <button type="button" class="text-gray-500 hover:text-gray-900" @click="rejectId = null">
                                        Fechar
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('gut-requests.update', $requestItem) }}" class="mt-4 space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="recusado">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Justificativa da recusa
                                        </label>
                                        <textarea name="rejection_reason"
                                            rows="4"
                                            required
                                            class="mt-1 w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                            placeholder="Explique o motivo da recusa"></textarea>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="rounded-md px-4 py-2 border" @click="rejectId = null">Cancelar</button>
                                        <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                            Recusar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
