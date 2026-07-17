<?php

use App\Models\Document;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Documents')] class extends Component {
    public string $search = '';

    public Tenant $tenant;

    public function mount(): void
    {
        $this->tenant = app('current.tenant');
    }

    /** @return Collection<int, Document> */
    #[Computed]
    public function documents(): Collection
    {
        return Document::query()
            ->forTenant($this->tenant)
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->latest()
            ->get();
    }

    public function delete(int $documentId): void
    {
        $document = Document::where('id', $documentId)->forTenant($this->tenant)->firstOrFail();

        $document->delete();

        Flux::toast(variant: 'success', text: __('Document deleted.'));
    }
}; ?>

<div class="py-6">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Documents') }}</flux:heading>
        <flux:button href="{{ route('docs.create') }}" variant="primary" wire:navigate>
            {{ __('New Document') }}
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search documents...')" class="mb-6 max-w-sm" />

    <div class="bg-white dark:bg-zinc-800 rounded-(--radius-card) shadow-(--shadow-elevated)">
        @if ($this->documents->isEmpty())
            <div class="p-8 text-center">
                <flux:subheading>{{ __('No documents found.') }}</flux:subheading>
            </div>
        @else
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->documents as $document)
                    <div class="flex items-center justify-between p-4">
                        <a href="{{ route('docs.show', $document) }}" wire:navigate class="flex-1">
                            <flux:heading>{{ $document->title }}</flux:heading>
                            <flux:subheading class="text-xs mt-1">
                                {{ $document->updated_at->diffForHumans() }}
                                &middot;
                                {{ $document->user?->name }}
                            </flux:subheading>
                        </a>
                        <div class="flex items-center gap-2">
                            <flux:button wire:navigate href="{{ route('docs.edit', $document) }}" variant="subtle" size="sm" icon="pencil" />
                            <flux:button wire:click="delete({{ $document->id }})" wire:confirm="{{ __('Are you sure?') }}" variant="subtle" size="sm" icon="trash" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
