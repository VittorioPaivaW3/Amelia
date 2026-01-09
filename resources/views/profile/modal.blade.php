@php
    $user = auth()->user();
@endphp

<div x-show="profileOpen" x-cloak class="fixed inset-0 z-40">
    <div class="absolute inset-0 bg-black/40" @click="profileOpen = false"></div>

    <div class="relative min-h-screen flex items-start justify-center p-4 sm:p-6">
        <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Perfil</h2>
                    <p class="text-xs text-gray-500">Atualize seus dados, foto e senha.</p>
                </div>
                <button type="button"
                        class="rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:text-gray-900 hover:border-gray-300"
                        @click="profileOpen = false">
                    Fechar
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-6 p-6">
                <aside class="space-y-1">
                    <button type="button"
                            class="w-full text-left rounded-lg px-3 py-2 text-sm font-semibold"
                            :class="profileTab === 'profile' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50'"
                            @click="profileTab = 'profile'">
                        Perfil
                    </button>
                    <button type="button"
                            class="w-full text-left rounded-lg px-3 py-2 text-sm font-semibold"
                            :class="profileTab === 'security' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50'"
                            @click="profileTab = 'security'">
                        Senha
                    </button>
                    <button type="button"
                            class="w-full text-left rounded-lg px-3 py-2 text-sm font-semibold"
                            :class="profileTab === 'danger' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50'"
                            @click="profileTab = 'danger'">
                        Conta
                    </button>
                </aside>

                <div class="min-h-[360px]">
                    <div x-show="profileTab === 'profile'" x-transition>
                        @include('profile.partials.update-profile-information-form', ['user' => $user])
                    </div>

                    <div x-show="profileTab === 'security'" x-transition>
                        @include('profile.partials.update-password-form')
                    </div>

                    <div x-show="profileTab === 'danger'" x-transition>
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
