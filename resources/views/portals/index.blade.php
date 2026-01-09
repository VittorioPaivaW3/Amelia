<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Portal {{ strtoupper($sector) }}
        </h2>
    </x-slot>

    @php
        $sectorOptions = collect($sectors ?? [])->mapWithKeys(function ($sectorItem) {
            return [$sectorItem => strtoupper($sectorItem)];
        })->all();
        $statusOptions = collect($statuses ?? [])->mapWithKeys(function ($statusItem) {
            $label = ucwords(str_replace('_', ' ', $statusItem));
            return [$statusItem => $label];
        })->all();
        $scoreOptions = collect(range(1, 5))->mapWithKeys(function ($value) {
            return [(string) $value => (string) $value];
        })->all();
    @endphp

    <div class="py-12" x-data="{ openId: null }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($requests->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        Nenhuma solicitacao neste setor ainda.
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach ($requests as $requestItem)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        #{{ $requestItem->id }}
                                    </div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        GUT {{ $requestItem->score }}
                                    </div>
                                </div>

                                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ str_replace('_', ' ', $requestItem->status) }}
                                </div>

                                <div class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ \Illuminate\Support\Str::limit($requestItem->message, 160) }}
                                </div>

                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $requestItem->created_at?->format('d/m/Y H:i') }}
                                </div>

                                <button type="button"
                                    class="w-full rounded-md btn-accent py-2 text-sm"
                                    @click="openId = {{ $requestItem->id }}">
                                    Ver detalhes
                                </button>
                            </div>
                        </div>

                        <div x-show="openId === {{ $requestItem->id }}"
                             x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center">
                            <div class="absolute inset-0 bg-black/50" @click="openId = null"></div>
                            <div class="relative bg-white dark:bg-gray-900 w-full max-w-2xl rounded-lg shadow-lg p-6">
                                <div class="flex items-start justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        Solicitacao #{{ $requestItem->id }}
                                    </h3>
                                    <button type="button" class="text-gray-500 hover:text-gray-900" @click="openId = null">
                                        Fechar
                                    </button>
                                </div>

                                <div class="mt-4 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                                    <div>
                                        <span class="font-semibold">Solicitante:</span>
                                        {{ $requestItem->user?->name }} ({{ $requestItem->user?->email }})
                                    </div>
                                    <div>
                                        <span class="font-semibold">Mensagem:</span>
                                        <div class="mt-1 whitespace-pre-line text-gray-900 dark:text-gray-100">
                                            {{ $requestItem->message }}
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('gut-requests.update', $requestItem) }}" class="mt-6 space-y-4">
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

                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        GUT atual: {{ $requestItem->score }}
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="rounded-md px-4 py-2 border" @click="openId = null">Cancelar</button>
                                        <button type="submit" class="rounded-md btn-accent px-4 py-2">
                                            Salvar
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
