<?php

use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('New Document')] class extends Component {
    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public Tenant $tenant;

    public function mount(): void
    {
        $this->tenant = app('current.tenant');
    }

    public function updatedTitle(string $value): void
    {
        if (blank($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:documents,slug,NULL,id,tenant_id,'.$this->tenant->id],
            'content' => ['nullable', 'string'],
        ]);

        $this->tenant->documents()->create([
            ...$validated,
            'user_id' => Auth::id(),
            'content' => $validated['content'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Document created.'));

        $this->redirect(route('docs.index'));
    }
}; ?>

<div class="py-6 max-w-2xl">
    <flux:heading size="xl" class="mb-6">{{ __('New Document') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('Title') }}</flux:label>
            <flux:input wire:model.blur="title" />
            <flux:error name="title" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Slug') }}</flux:label>
            <flux:input wire:model="slug" />
            <flux:description>{{ __('Used in the document URL. Auto-generated from the title if left empty.') }}</flux:description>
            <flux:error name="slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Content') }}</flux:label>
            <flux:textarea wire:model="content" rows="12" />
            <flux:description>{{ __('Markdown is supported.') }}</flux:description>
            <flux:error name="content" />
        </flux:field>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Create Document') }}</flux:button>
            <flux:button wire:navigate href="{{ route('docs.index') }}">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
