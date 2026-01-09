<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (($role ?? null) === 'user')
                        <div class="text-lg font-semibold mb-4">Minhas solicitacoes</div>

                        @if (($requests ?? collect())->isEmpty())
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Nenhuma solicitacao enviada ainda.
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach ($requests as $requestItem)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                #{{ $requestItem->id }} - {{ $requestItem->created_at?->format('d/m/Y H:i') }}
                                            </div>
                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                GUT {{ $requestItem->score }}
                                            </div>
                                        </div>
                                        <div class="mt-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ str_replace('_', ' ', $requestItem->status) }}
                                        </div>
                                        <div class="mt-2 text-sm text-gray-900 dark:text-gray-100">
                                            {{ \Illuminate\Support\Str::limit($requestItem->message, 180) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @elseif (($role ?? null) === 'admin')
                        <div class="text-lg font-semibold mb-4">Todas as solicitacoes</div>

                        @if (($requests ?? collect())->isEmpty())
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Nenhuma solicitacao registrada ainda.
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach ($requests as $requestItem)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                #{{ $requestItem->id }} - {{ $requestItem->created_at?->format('d/m/Y H:i') }}
                                            </div>
                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                GUT {{ $requestItem->score }}
                                            </div>
                                        </div>
                                        @php
                                            $status = $requestItem->status ?? '';
                                            $sector = $requestItem->sector ?? '';
                                            $statusLabel = $status !== '' ? str_replace('_', ' ', $status) : 'sem status';
                                            $sectorLabel = $sector !== '' ? strtoupper($sector) : 'SEM SETOR';
                                        @endphp
                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide">
                                            <span @class([
                                                'inline-flex items-center rounded-full border px-2.5 py-0.5',
                                                'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700' => $status === 'novo' || $status === '',
                                                'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-900' => $status === 'em_andamento',
                                                'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-900' => $status === 'concluido',
                                            ])>
                                                {{ $statusLabel }}
                                            </span>
                                            <span @class([
                                                'inline-flex items-center rounded-full border px-2.5 py-0.5',
                                                'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-200 dark:border-red-900' => $sector === 'juridico',
                                                'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-200 dark:border-blue-900' => $sector === 'mkt',
                                                'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-900' => $sector === 'rh',
                                                'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700' => ! in_array($sector, ['juridico', 'mkt', 'rh'], true),
                                            ])>
                                                {{ $sectorLabel }}
                                            </span>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $requestItem->user?->name ?? 'Usuario removido' }}
                                        </div>
                                        <div class="mt-2 text-sm text-gray-900 dark:text-gray-100">
                                            {{ \Illuminate\Support\Str::limit($requestItem->message, 180) }}
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
            </div>
        </div>
    </div>
</x-app-layout>
