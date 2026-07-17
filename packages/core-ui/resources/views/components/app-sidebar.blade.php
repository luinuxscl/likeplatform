<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 max-lg:hidden!">
    <flux:sidebar.header>
        <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.group :heading="__('Navigation')" class="grid">
            <flux:sidebar.item
                icon="home"
                :href="route('dashboard')"
                :current="request()->routeIs('dashboard')"
                wire:navigate
            >
                {{ __('Dashboard') }}
            </flux:sidebar.item>
        </flux:sidebar.group>

        <flux:sidebar.group :heading="__('Applications')" class="grid">
            <x-app-switcher />
        </flux:sidebar.group>
    </flux:sidebar.nav>

    <flux:spacer />

    <x-desktop-user-menu
        class="hidden lg:flex! w-full border-t border-zinc-200 dark:border-zinc-700"
        :name="auth()->user()->name"
    />
</flux:sidebar>
