<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email -->
        <div>
            <x-input-label for="email" :value="__('Email')" class="text-white" />
            <x-text-input id="email" class="block mt-1 w-full" 
            type="email" 
            name="email" :value="old('email')" 
            required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Senha')" class="text-white" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
       <div class="block mt-4">
    <label class="inline-flex items-center cursor-pointer">
        <input id="remember_me" type="checkbox" class="sr-only peer" name="remember">
        
        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer 
                    peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full 
                    peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] 
                    after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 
                    after:transition-all 
                    peer-checked:bg-blue-600"> 
        </div>
        
        <span class="ms-3 text-sm font-medium text-gray-600 dark:text-gray-300 text-white">
            {{ __('Salvar acesso') }}
        </span>
    </label>
</div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('register'))
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('register') }}">
                    {{ __('Criar conta') }}
                </a>
            @endif

            <x-primary-button class="ms-3 animate-camaleao hover:bg-verdes-verde_folha text-white font-bold py-3 px-6 rounded-full shadow-lg transition duration-500 ease-in-out ">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
