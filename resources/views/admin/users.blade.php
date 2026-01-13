<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Admin
        </h2>
    </x-slot>

    @php
        $staffRoleOptions = collect($staffRoles ?? [])->mapWithKeys(function ($role) {
            return [$role => strtoupper($role)];
        })->all();
        $roleOptions = collect($roles ?? [])->mapWithKeys(function ($role) {
            return [$role => strtoupper($role)];
        })->all();
        $allUsers = $users ?? collect();
        $staffUsers = $allUsers->filter(fn ($user) => $user->role !== 'user');
        $regularUsers = $allUsers->filter(fn ($user) => $user->role === 'user');
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-visible shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="text-lg font-semibold">Criar funcionario</div>

                        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 space-y-4">
                            @csrf

                            <div>
                                <x-input-label for="name" :value="'Nome'" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" :value="'Email (opcional)'" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" autocomplete="username" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="cpf" :value="'CPF'" />
                                <x-text-input id="cpf" class="block mt-1 w-full" type="text" name="cpf" :value="old('cpf')" required inputmode="numeric" autocomplete="off" />
                                <x-input-error :messages="$errors->get('cpf')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="password" :value="'Senha'" />
                                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="password_confirmation" :value="'Confirmar senha'" />
                                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="role" :value="'Setor'" />
                                <x-select-dropdown id="role" name="role" class="mt-1" :options="$staffRoleOptions" value="mkt" :theme-by-value="true" />
                                <x-input-error :messages="$errors->get('role')" class="mt-2" />
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-md btn-accent px-4 py-2 text-sm">
                                    Criar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-visible shadow-sm sm:rounded-lg" x-data="{ tab: 'staff', editId: null, deleteId: null }">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="text-lg font-semibold">Usuarios</div>

                        @if ($allUsers->isEmpty())
                            <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                Nenhum usuario cadastrado.
                            </div>
                        @else
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button"
                                    class="rounded-full px-4 py-2 text-sm font-semibold transition border"
                                    :class="tab === 'staff'
                                        ? 'btn-accent border-transparent'
                                        : 'border-gray-200 text-gray-600 dark:text-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900'"
                                    @click="tab = 'staff'">
                                    Funcionarios ({{ $staffUsers->count() }})
                                </button>
                                <button type="button"
                                    class="rounded-full px-4 py-2 text-sm font-semibold transition border theme-default"
                                    :class="tab === 'regular'
                                        ? 'btn-accent border-transparent'
                                        : 'border-gray-200 text-gray-600 dark:text-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900'"
                                    @click="tab = 'regular'">
                                    Usuarios ({{ $regularUsers->count() }})
                                </button>
                            </div>
                            <div class="mt-4 overflow-visible">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="text-left text-gray-500 dark:text-gray-400">
                                        <tr>
                                            <th class="py-2 pr-4 font-medium">Usuario</th>
                                            <th class="py-2 pr-4 font-medium">Role atual</th>
                                            <th class="py-2 pr-4 font-medium">Alterar role</th>
                                            <th class="py-2 text-right font-medium">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700" x-show="tab === 'staff'" x-cloak>
                                        @if ($staffUsers->isEmpty())
                                            <tr>
                                                <td colspan="4" class="py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    Nenhum funcionario cadastrado.
                                                </td>
                                            </tr>
                                        @else
                                            @foreach ($staffUsers as $user)
                                                <tr>
                                                    <td class="py-3 pr-4">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $user->name }}
                                                        </div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 break-all">
                                                            {{ $user->email }}
                                                        </div>
                                                        @if ($user->cpf)
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                CPF: {{ $user->cpf }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">
                                                        {{ strtoupper($user->role) }}
                                                    </td>
                                                    <td class="py-3 pr-4">
                                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                                            @csrf
                                                            @method('PATCH')
                                                            <x-select-dropdown name="role" class="w-full sm:w-44" :options="$roleOptions" :value="$user->role" :theme-by-value="true" :use-old="false" />
                                                            <button type="submit" class="rounded-md btn-accent px-3 py-2 text-xs">
                                                                Atualizar
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td class="py-3 text-right">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button type="button"
                                                                class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                                @click="editId = {{ $user->id }}">
                                                                Editar
                                                            </button>
                                                            <button type="button"
                                                                class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
                                                                @click="deleteId = {{ $user->id }}">
                                                                Excluir
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 theme-default" x-show="tab === 'regular'" x-cloak>
                                        @if ($regularUsers->isEmpty())
                                            <tr>
                                                <td colspan="4" class="py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    Nenhum usuario normal cadastrado.
                                                </td>
                                            </tr>
                                        @else
                                            @foreach ($regularUsers as $user)
                                            <tr>
                                                <td class="py-3 pr-4">
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $user->name }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 break-all">
                                                        {{ $user->email }}
                                                    </div>
                                                    @if ($user->cpf)
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            CPF: {{ $user->cpf }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">
                                                    {{ strtoupper($user->role) }}
                                                </td>
                                                <td class="py-3 pr-4">
                                                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                                        @csrf
                                                        @method('PATCH')
                                                        <x-select-dropdown name="role" class="w-full sm:w-44" :options="$roleOptions" :value="$user->role" :theme-by-value="true" :use-old="false" />
                                                        <button type="submit" class="rounded-md btn-accent px-3 py-2 text-xs">
                                                            Atualizar
                                                        </button>
                                                    </form>
                                                </td>
                                                <td class="py-3 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button type="button"
                                                            class="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                            @click="editId = {{ $user->id }}">
                                                            Editar
                                                        </button>
                                                        <button type="button"
                                                            class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
                                                            @click="deleteId = {{ $user->id }}">
                                                            Excluir
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            @foreach ($allUsers as $user)
                                <div x-show="editId === {{ $user->id }}"
                                    x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center">
                                    <div class="absolute inset-0 bg-black/50" @click="editId = null"></div>
                                    <div class="relative bg-white dark:bg-gray-900 w-[calc(100%-2rem)] sm:w-full max-w-lg max-h-[85vh] overflow-y-auto no-scrollbar rounded-lg shadow-lg p-6">
                                        <div class="flex items-start justify-between gap-4">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                Editar usuario
                                            </h3>
                                            <button type="button" class="text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white" @click="editId = null">
                                                Fechar
                                            </button>
                                        </div>

                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4 space-y-4">
                                            @csrf
                                            @method('PATCH')

                                            <div>
                                                <x-input-label :for="'edit-name-'.$user->id" :value="'Nome'" />
                                                <x-text-input :id="'edit-name-'.$user->id" class="block mt-1 w-full" type="text" name="name" :value="$user->name" required />
                                            </div>

                                            <div>
                                                <x-input-label :for="'edit-email-'.$user->id" :value="'Email (opcional)'" />
                                                <x-text-input :id="'edit-email-'.$user->id" class="block mt-1 w-full" type="email" name="email" :value="$user->email" />
                                            </div>

                                            <div>
                                                <x-input-label :for="'edit-cpf-'.$user->id" :value="'CPF'" />
                                                <x-text-input :id="'edit-cpf-'.$user->id" class="block mt-1 w-full" type="text" name="cpf" :value="$user->cpf" required inputmode="numeric" autocomplete="off" />
                                            </div>

                                            <div>
                                                <x-input-label :for="'edit-role-'.$user->id" :value="'Role'" />
                                                <x-select-dropdown :id="'edit-role-'.$user->id" name="role" class="mt-1" :options="$roleOptions" :value="$user->role" :theme-by-value="true" :use-old="false" />
                                            </div>

                                            <div>
                                                <x-input-label :for="'edit-password-'.$user->id" :value="'Nova senha (opcional)'" />
                                                <x-text-input :id="'edit-password-'.$user->id" class="block mt-1 w-full" type="password" name="password" autocomplete="new-password" />
                                            </div>

                                            <div>
                                                <x-input-label :for="'edit-password-confirm-'.$user->id" :value="'Confirmar nova senha'" />
                                                <x-text-input :id="'edit-password-confirm-'.$user->id" class="block mt-1 w-full" type="password" name="password_confirmation" autocomplete="new-password" />
                                            </div>

                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="rounded-md border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm" @click="editId = null">
                                                    Cancelar
                                                </button>
                                                <button type="submit" class="rounded-md btn-accent px-4 py-2 text-sm">
                                                    Salvar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div x-show="deleteId === {{ $user->id }}"
                                    x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center">
                                    <div class="absolute inset-0 bg-black/50" @click="deleteId = null"></div>
                                    <div class="relative bg-white dark:bg-gray-900 w-[calc(100%-2rem)] sm:w-full max-w-md rounded-lg shadow-lg p-6">
                                        <div class="flex items-start justify-between gap-4">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                Excluir usuario
                                            </h3>
                                            <button type="button" class="text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white" @click="deleteId = null">
                                                Fechar
                                            </button>
                                        </div>
                                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                            Tem certeza que deseja excluir {{ $user->name }} ({{ $user->email }})?
                                        </p>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4 flex justify-end gap-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="rounded-md border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm" @click="deleteId = null">
                                                Cancelar
                                            </button>
                                            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
