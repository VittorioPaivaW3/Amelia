@php
    $role = $role ?? auth()->user()?->role;
    $requests = $requests ?? collect();
    $isStaff = in_array($role, ['mkt', 'juridico', 'rh'], true);
@endphp

<div class="space-y-4 text-gray-900 dark:text-gray-100">
    @if (($role ?? null) === 'user' || $isStaff)
        <div class="text-lg font-semibold">Minhas solicitacoes</div>

        @if ($requests->isEmpty())
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Nenhuma solicitacao enviada ainda.
            </div>
        @else
            <div class="request-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6" x-data="{ openId: null, cancelId: null }">
                @foreach ($requests as $requestItem)
                    @php
                        $status = $requestItem->status ?? '';
                        $statusLabel = $status !== '' ? str_replace('_', ' ', $status) : 'sem status';
                        $sectorValue = $requestItem->sector ?? '';
                        $palette = [
                            'juridico' => ['accent' => '#ef4444', 'deep' => '#b91c1c'],
                            'mkt' => ['accent' => '#3b82f6', 'deep' => '#1d4ed8'],
                            'rh' => ['accent' => '#f59e0b', 'deep' => '#b45309'],
                            'default' => ['accent' => 'var(--accent)', 'deep' => 'var(--accent-hover)'],
                        ];
                        $isRejected = in_array($status, ['recusado', 'cancelado'], true);
                        $isCanceled = $status === 'cancelado';
                        $isAccepted = $status === 'em_andamento';
                        $canEdit = $status === 'novo' || $status === '';
                        $canCancel = $canEdit && ! $requestItem->accepted_by;
                        $reasonLabel = $isCanceled ? 'Motivo do cancelamento' : 'Justificativa da recusa';
                        $colors = $palette[$sectorValue] ?? $palette['default'];
                        $cardClass = 'request-card';
                        if ($isRejected) {
                            $cardClass .= ' request-card--rejected';
                        } elseif ($isAccepted) {
                            $cardClass .= ' request-card--accepted';
                        }
                        $cardStyle = $isRejected
                            ? 'background: #e5e7eb;--sector-accent: #111827;--sector-accent-deep: #111827;'
                            : 'background: '.$colors['accent'].';--sector-accent: '.$colors['accent'].';--sector-accent-deep: '.$colors['deep'].';';
                    @endphp
                    <div class="{{ $cardClass }}" style="{{ $cardStyle }}">
                        <div class="request-card__hero">
                            @if ($canCancel)
                                <button type="button"
                                    class="request-card__reject"
                                    title="Cancelar"
                                    @click="cancelId = {{ $requestItem->id }}">
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
                        </div>
                        <div class="request-card__actions">
                            <button type="button"
                                class="request-card__details"
                                @click="openId = {{ $requestItem->id }}">
                                Detalhes
                            </button>
                            @if ($canEdit)
                                <a href="{{ route('chat', ['request_id' => $requestItem->id]) }}"
                                   class="request-card__accept"
                                   aria-label="Editar">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.2a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path>
                                    </svg>
                                </a>
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
                                    $acceptedUser = $requestItem->acceptedBy;
                                    $photoPath = $acceptedUser?->profile_photo_path;
                                    $photoUrl = $photoPath ? asset('storage/'.$photoPath) : null;
                                    $initials = $acceptedUser
                                        ? collect(explode(' ', trim($acceptedUser->name)))
                                            ->filter()
                                            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                            ->take(2)
                                            ->implode('')
                                        : '?';
                                @endphp
                                <div class="request-modal__body">
                                    <div class="request-modal__grid">
                                        <div class="request-modal__card">
                                            <div class="request-modal__label">Atendente</div>
                                            <div class="mt-3 flex items-center gap-3">
                                                @if ($photoUrl)
                                                    <img src="{{ $photoUrl }}" alt="{{ $acceptedUser?->name ?? 'Atendente' }}" class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700">
                                                @else
                                                    <div class="h-10 w-10 rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200 flex items-center justify-center text-xs font-bold">
                                                        {{ $initials }}
                                                    </div>
                                                @endif
                                                <div class="request-modal__value">
                                                    {{ $acceptedUser?->name ?? 'Aguardando aceite' }}
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

                    <div x-show="cancelId === {{ $requestItem->id }}"
                         x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/50" @click="cancelId = null"></div>
                        <div class="relative bg-white dark:bg-gray-900 w-[calc(100%-2rem)] sm:w-full max-w-md rounded-lg shadow-lg p-6">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Cancelar solicitacao #{{ $requestItem->id }}
                                </h3>
                                <button type="button" class="text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white" @click="cancelId = null">
                                    Fechar
                                </button>
                            </div>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                Tem certeza que deseja cancelar este pedido?
                            </p>
                            <form method="POST" action="{{ route('gut-requests.cancel', $requestItem) }}" class="mt-4 flex justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <button type="button" class="rounded-md border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm" @click="cancelId = null">
                                    Voltar
                                </button>
                                <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                    Cancelar pedido
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @elseif (($role ?? null) === 'admin')
        @php
            $sectorOptions = collect(['mkt', 'juridico', 'rh'])->mapWithKeys(function ($sectorItem) {
                return [$sectorItem => strtoupper($sectorItem)];
            })->all();
            $statusOptions = collect(['novo', 'em_andamento', 'concluido', 'recusado', 'cancelado'])->mapWithKeys(function ($statusItem) {
                $label = ucwords(str_replace('_', ' ', $statusItem));
                return [$statusItem => $label];
            })->all();
            $scoreOptions = collect(range(1, 5))->mapWithKeys(function ($value) {
                return [(string) $value => (string) $value];
            })->all();
        @endphp
        <div class="text-lg font-semibold">Todas as solicitacoes</div>

        @if ($requests->isEmpty())
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Nenhuma solicitacao registrada ainda.
            </div>
        @else
            <div class="request-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6" x-data="{ detailsId: null, rejectId: null }">
                @foreach ($requests as $requestItem)
                    @php
                        $status = $requestItem->status ?? '';
                        $sector = $requestItem->sector ?? '';
                        $statusLabel = $status !== '' ? str_replace('_', ' ', $status) : 'sem status';
                        $sectorLabel = $sector !== '' ? strtoupper($sector) : 'SEM SETOR';
                        $canAct = $status === 'novo' || $status === '';
                        $palette = [
                            'juridico' => ['accent' => '#ef4444', 'deep' => '#b91c1c'],
                            'mkt' => ['accent' => '#3b82f6', 'deep' => '#1d4ed8'],
                            'rh' => ['accent' => '#f59e0b', 'deep' => '#b45309'],
                            'default' => ['accent' => '#6b7280', 'deep' => '#374151'],
                        ];
                        $isRejected = in_array($status, ['recusado', 'cancelado'], true);
                        $isCanceled = $status === 'cancelado';
                        $isAccepted = $status === 'em_andamento';
                        $reasonLabel = $isCanceled ? 'Motivo do cancelamento' : 'Justificativa da recusa';
                        $colors = $palette[$sector] ?? $palette['default'];
                        $cardClass = 'request-card';
                        if ($isRejected) {
                            $cardClass .= ' request-card--rejected';
                        } elseif ($isAccepted) {
                            $cardClass .= ' request-card--accepted';
                        }
                        $cardStyle = $isRejected
                            ? 'background: #e5e7eb;--sector-accent: #111827;--sector-accent-deep: #111827;'
                            : 'background: '.$colors['accent'].';--sector-accent: '.$colors['accent'].';--sector-accent-deep: '.$colors['deep'].';';
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
                                <span @class([
                                    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide',
                                    'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-200 dark:border-red-900' => $sector === 'juridico',
                                    'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-200 dark:border-blue-900' => $sector === 'mkt',
                                    'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-900' => $sector === 'rh',
                                    'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700' => ! in_array($sector, ['juridico', 'mkt', 'rh'], true),
                                ])>
                                    {{ $sectorLabel }}
                                </span>
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
                                @click="detailsId = {{ $requestItem->id }}">
                                Detalhes
                            </button>
                            @if ($canAct)
                                <form method="POST" action="{{ route('gut-requests.update', $requestItem) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="sector" value="{{ $requestItem->sector }}">
                                    <input type="hidden" name="gravity" value="{{ $requestItem->gravity }}">
                                    <input type="hidden" name="urgency" value="{{ $requestItem->urgency }}">
                                    <input type="hidden" name="trend" value="{{ $requestItem->trend }}">
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

                    <div x-show="detailsId === {{ $requestItem->id }}"
                         x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/50" @click="detailsId = null"></div>
                        <div class="relative w-[calc(100%-2rem)] sm:w-full max-w-4xl max-h-[85vh] overflow-y-auto no-scrollbar">
                            <div class="request-modal" style="--modal-accent: {{ $colors['accent'] ?? '#6b7280' }};">
                                <div class="request-modal__header">
                                    <div>
                                        <div class="request-modal__eyebrow">Detalhes do chamado</div>
                                        <h3 class="request-modal__title">Solicitacao #{{ $requestItem->id }}</h3>
                                        <div class="request-modal__meta">
                                            <span class="request-modal__chip">{{ $sectorLabel }}</span>
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
                                    <button type="button" class="request-modal__close" @click="detailsId = null">
                                        Fechar
                                    </button>
                                </div>

                                <div class="request-modal__body">
                                    <div class="request-modal__grid">
                                        <div class="request-modal__card">
                                            <div class="request-modal__label">Solicitante</div>
                                            <div class="request-modal__value">{{ $requestItem->user?->name ?? 'Usuario' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $requestItem->user?->email }}</div>
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

                                    <div class="request-modal__section border-t border-gray-200 dark:border-gray-700 pt-4">
                                        <div class="request-modal__label">Atualizar dados</div>
                                        <form method="POST" action="{{ route('gut-requests.update', $requestItem) }}" class="mt-3 space-y-4">
                                            @csrf
                                            @method('PATCH')

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Setor</label>
                                                    <x-select-dropdown name="sector" class="mt-1" :options="$sectorOptions" :value="$requestItem->sector" />
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                                    <x-select-dropdown name="status" class="mt-1" :options="$statusOptions" :value="$requestItem->status" />
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gravidade</label>
                                                    <x-select-dropdown name="gravity" class="mt-1" :options="$scoreOptions" :value="$requestItem->gravity" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Urgencia</label>
                                                    <x-select-dropdown name="urgency" class="mt-1" :options="$scoreOptions" :value="$requestItem->urgency" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tendencia</label>
                                                    <x-select-dropdown name="trend" class="mt-1" :options="$scoreOptions" :value="$requestItem->trend" />
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Justificativa da recusa (obrigatorio se status recusado)
                                                </label>
                                                <textarea name="rejection_reason"
                                                    rows="3"
                                                    class="mt-1 w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                                    placeholder="Descreva o motivo da recusa">{{ old('rejection_reason', $requestItem->rejection_reason) }}</textarea>
                                            </div>

                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                GUT atual: {{ $requestItem->score }}
                                            </div>

                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="rounded-md px-4 py-2 border" @click="detailsId = null">Cancelar</button>
                                                <button type="submit" class="rounded-md btn-accent px-4 py-2">
                                                    Salvar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
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
                                <input type="hidden" name="sector" value="{{ $requestItem->sector }}">
                                <input type="hidden" name="gravity" value="{{ $requestItem->gravity }}">
                                <input type="hidden" name="urgency" value="{{ $requestItem->urgency }}">
                                <input type="hidden" name="trend" value="{{ $requestItem->trend }}">
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
    @else
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Use o menu para acessar o portal do seu setor.
        </div>
    @endif
</div>
