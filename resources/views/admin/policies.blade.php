<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Politicas
        </h2>
    </x-slot>

    @php
        $sectorOptions = collect($sectors ?? [])->mapWithKeys(function ($sector) {
            return [$sector => strtoupper($sector)];
        })->all();
    @endphp

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-800 px-4 py-2 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 text-red-800 px-4 py-2 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-visible shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="text-lg font-semibold">Atualizar politica (PDF)</div>

                    <form method="POST" action="{{ route('admin.policies.store') }}"
                        class="mt-4 space-y-4"
                        enctype="multipart/form-data"
                        x-data="{ sector: @json(old('sector', 'rh')), fileName: '' }"
                        :class="`select-theme-${sector}`">
                        @csrf

                        <div>
                            <x-input-label for="sector" :value="'Setor'" />
                            <x-select-dropdown id="sector" name="sector" class="mt-1" :options="$sectorOptions" value="rh" :theme-by-value="true" x-model="sector" />
                            <x-input-error :messages="$errors->get('sector')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="title" :value="'Titulo (opcional)'" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" autocomplete="off" />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="document" :value="'Arquivo PDF'" />
                            <div class="mt-1 flex flex-wrap items-center gap-3">
                                <input id="document"
                                    name="document"
                                    type="file"
                                    accept="application/pdf"
                                    class="sr-only"
                                    required
                                    @change="fileName = $event.target.files?.[0]?.name || ''">
                                <label for="document" class="inline-flex items-center rounded-full btn-accent px-4 py-2 text-xs font-semibold cursor-pointer">
                                    Escolher arquivo
                                </label>
                                <span class="text-sm text-gray-600 dark:text-gray-300" x-text="fileName || 'Nenhum arquivo escolhido'"></span>
                            </div>
                            <x-input-error :messages="$errors->get('document')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-md btn-accent px-4 py-2 text-sm">
                                Enviar PDF
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-visible shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-4">
                    <div class="text-lg font-semibold">Politicas ativas</div>

                    @if (($documents ?? collect())->isEmpty())
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Nenhum documento cadastrado.
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($sectorOptions as $sector => $label)
                                @php
                                    $sectorDocs = $documents[$sector] ?? collect();
                                    $active = $sectorDocs->firstWhere('is_active', true);
                                    $history = $sectorDocs->filter(function ($doc) use ($active) {
                                        return ! $active || $doc->id !== $active->id;
                                    });
                                @endphp
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 select-theme-{{ $sector }}">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $label }}
                                    </div>
                                    @if ($active)
                                        <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $active->title ?? 'Documento ativo' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Atualizado em {{ $active->created_at?->format('d/m/Y H:i') }}
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <a href="{{ route('admin.policies.view', $active) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="rounded-md btn-accent px-3 py-2 text-xs font-semibold">
                                                Ver PDF
                                            </a>
                                            <a href="{{ route('admin.policies.download', $active) }}"
                                                class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200">
                                                Baixar
                                            </a>
                                        </div>
                                    @else
                                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            Nenhuma politica ativa.
                                        </div>
                                    @endif
                                    @if ($history->isNotEmpty())
                                        <div class="mt-4 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                            Historico recente
                                        </div>
                                        <div class="mt-2 space-y-2">
                                            @foreach ($history->take(3) as $doc)
                                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-600 dark:text-gray-300">
                                                    <span>{{ $doc->title ?? 'Documento' }}</span>
                                                    <span class="text-gray-400 dark:text-gray-500">
                                                        {{ $doc->created_at?->format('d/m/Y H:i') }}
                                                    </span>
                                                    <span class="flex items-center gap-2">
                                                        <a href="{{ route('admin.policies.view', $doc) }}"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="text-emerald-600 hover:text-emerald-500">
                                                            Ver
                                                        </a>
                                                        <a href="{{ route('admin.policies.download', $doc) }}"
                                                            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                                            Baixar
                                                        </a>
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
