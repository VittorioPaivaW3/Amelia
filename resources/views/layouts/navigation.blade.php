<nav x-data="{ open: false }" class="app-nav">
    @php
        $user = Auth::user();
        $role = $user?->role ?? null;
        $photoPath = $user?->profile_photo_path;
        $photoUrl = $photoPath ? asset('storage/'.$photoPath) : null;
        $initials = $user
            ? collect(explode(' ', trim($user->name)))
                ->filter()
                ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                ->take(2)
                ->implode('')
            : '';
    @endphp
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="app-nav-shell">
            <div class="app-nav-inner">
                <div class="flex items-center gap-4">
                    <a href="{{ route('dashboard') }}" class="app-nav-logo">
                        <img src="{{ asset('img/Amelia.png') }}" alt="Amelia" class="h-6 w-6">
                    </a>

                    <div class="hidden sm:flex app-nav-links">
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                        @if ($role === 'mkt')
                            <x-nav-link :href="route('portal.sector', ['sector' => 'mkt'])" :active="request()->routeIs('portal.sector') && request()->route('sector') === 'mkt'">
                                Portal MKT
                            </x-nav-link>
                        @elseif ($role === 'juridico')
                            <x-nav-link :href="route('portal.sector', ['sector' => 'juridico'])" :active="request()->routeIs('portal.sector') && request()->route('sector') === 'juridico'">
                                Portal Juridico
                            </x-nav-link>
                        @elseif ($role === 'rh')
                            <x-nav-link :href="route('portal.sector', ['sector' => 'rh'])" :active="request()->routeIs('portal.sector') && request()->route('sector') === 'rh'">
                                Portal RH
                            </x-nav-link>
                        @endif
                        @if ($role === 'admin')
                            <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                Admin
                            </x-nav-link>
                        @endif
                        <x-nav-link :href="route('chat')" :active="request()->routeIs('chat')">
                            Chat
                        </x-nav-link>
                    </div>
                </div>

                <div class="hidden sm:flex items-center gap-3">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="app-nav-user">
                                @if ($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="{{ $user->name }}" class="app-nav-avatar">
                                @else
                                    <span class="app-nav-avatar app-nav-avatar-fallback">
                                        {{ $initials }}
                                    </span>
                                @endif
                                <span class="app-nav-user-pill">
                                    <span class="app-nav-user-name">{{ $user->name }}</span>
                                    <svg class="app-nav-user-chevron h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link href="#"
                                             @click.prevent="$dispatch('open-profile-modal')">
                                {{ __('Perfil') }}
                            </x-dropdown-link>

                            <x-dropdown-link :href="route('logout')">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>

                <div class="sm:hidden">
                    <button @click="open = ! open" class="app-nav-hamburger" aria-label="Menu">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden app-nav-mobile">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @if ($role === 'mkt')
                <x-responsive-nav-link :href="route('portal.sector', ['sector' => 'mkt'])" :active="request()->routeIs('portal.sector') && request()->route('sector') === 'mkt'">
                    Portal MKT
                </x-responsive-nav-link>
            @elseif ($role === 'juridico')
                <x-responsive-nav-link :href="route('portal.sector', ['sector' => 'juridico'])" :active="request()->routeIs('portal.sector') && request()->route('sector') === 'juridico'">
                    Portal Juridico
                </x-responsive-nav-link>
            @elseif ($role === 'rh')
                <x-responsive-nav-link :href="route('portal.sector', ['sector' => 'rh'])" :active="request()->routeIs('portal.sector') && request()->route('sector') === 'rh'">
                    Portal RH
                </x-responsive-nav-link>
            @endif
            @if ($role === 'admin')
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    Admin
                </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('chat')" :active="request()->routeIs('chat')">
                Chat
            </x-responsive-nav-link>
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link href="#"
                                       @click.prevent="open = false; $dispatch('open-profile-modal')">
                    {{ __('Perfil') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('logout')">
                    {{ __('Log Out') }}
                </x-responsive-nav-link>
            </div>
        </div>
    </div>
</nav>
