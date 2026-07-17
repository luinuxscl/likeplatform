<?php

use App\Models\Document;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Edit Document')] class extends Component {
    public Document $doc;

    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public Tenant $tenant;

    public function mount(string $document): void
    {
        $this->tenant = app('current.tenant');

        $this->doc = Document::query()
            ->forTenant($this->tenant)
            ->where('slug', $document)
            ->firstOrFail();

        $this->title = $this->doc->title;
        $this->slug = $this->doc->slug;
        $this->content = $this->doc->content ?? '';
    }

    public function updatedTitle(string $value): void
    {
        if ($this->slug === Str::slug($this->doc->title)) {
            $this->slug = Str::slug($value);
        }
    }

    public function update(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:documents,slug,'.$this->doc->id.',id,tenant_id,'.$this->tenant->id],
            'content' => ['nullable', 'string'],
        ]);

        $this->doc->update([
            ...$validated,
            'content' => $validated['content'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Document updated.'));

        $this->redirect(route('docs.index'));
    }

    public function delete(): void
    {
        $this->doc->delete();

        Flux::toast(variant: 'success', text: __('Document deleted.'));

        $this->redirect(route('docs.index'));
    }
}; ?>

<div class="py-6 max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Edit Document') }}</flux:heading>

        <flux:button wire:click="delete" wire:confirm="{{ __('Are you sure?') }}" variant="danger" size="sm" icon="trash">
            {{ __('Delete') }}
        </flux:button>
    </div>

    <form wire:submit="update" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('Title') }}</flux:label>
            <flux:input wire:model.blur="title" />
            <flux:error name="title" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Slug') }}</flux:label>
            <flux:input wire:model="slug" />
            <flux:description>{{ __('Used in the document URL. Auto-updates with the title when they match.') }}</flux:description>
            <flux:error name="slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Content') }}</flux:label>
            <flux:textarea wire:model="content" rows="12" />
            <flux:description>{{ __('Markdown is supported.') }}</flux:description>
            <flux:error name="content" />
        </flux:field>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            <flux:button wire:navigate href="{{ route('docs.show', $doc) }}">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
