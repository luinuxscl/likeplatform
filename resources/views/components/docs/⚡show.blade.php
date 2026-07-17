<?php

use App\Models\Document;
use App\Models\Tenant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('View Document')] class extends Component {
    public Document $doc;

    public Tenant $tenant;

    public function mount(string $document): void
    {
        $this->tenant = app('current.tenant');

        $this->doc = Document::query()
            ->forTenant($this->tenant)
            ->where('slug', $document)
            ->firstOrFail();
    }

    #[Computed]
    public function renderedContent(): string
    {
        return $this->doc->toHtml();
    }
}; ?>

<div class="py-6 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <flux:button wire:navigate href="{{ route('docs.index') }}" variant="subtle" icon="arrow-left" size="sm">
            {{ __('Back to Documents') }}
        </flux:button>

        <flux:button wire:navigate href="{{ route('docs.edit', $doc) }}" variant="primary" size="sm" icon="pencil">
            {{ __('Edit') }}
        </flux:button>
    </div>

    <article>
        <flux:heading size="xl" class="mb-6">{{ $doc->title }}</flux:heading>

        <flux:subheading class="mb-8 text-xs">
            {{ $doc->user?->name }}
            &middot;
            {{ $doc->updated_at->format('M j, Y') }}
        </flux:subheading>

        @if ($doc->content)
            <div class="prose dark:prose-invert max-w-none">
                {!! $this->renderedContent !!}
            </div>
        @else
            <flux:subheading>{{ __('No content yet.') }}</flux:subheading>
        @endif
    </article>
</div>
