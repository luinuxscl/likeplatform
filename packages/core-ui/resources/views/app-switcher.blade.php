@if ($spokes->isNotEmpty())
    @foreach ($spokes as $spoke)
        <flux:sidebar.item
            :icon="$spoke->safeIcon()"
            :href="$spoke->path()"
            :current="optional($currentSpoke)->slug === $spoke->slug"
            wire:navigate
        >
            {{ $spoke->name }}
        </flux:sidebar.item>
    @endforeach
@else
    <flux:sidebar.item icon="squares-2x2" disabled>
        {{ __('No spokes available') }}
    </flux:sidebar.item>
@endif