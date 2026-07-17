<?php

use App\Models\Invoice;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Invoices')] class extends Component {
    public string $search = '';

    public string $statusFilter = '';

    public Tenant $tenant;

    public function mount(): void
    {
        $this->tenant = app('current.tenant');
    }

    /** @return Collection<int, Invoice> */
    #[Computed]
    public function invoices(): Collection
    {
        return Invoice::query()
            ->forTenant($this->tenant)
            ->when($this->search, fn ($query) => $query->where(function ($q) {
                $q->where('client_name', 'like', '%'.$this->search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->get();
    }

    public function delete(int $id): void
    {
        $invoice = Invoice::where('id', $id)->forTenant($this->tenant)->firstOrFail();

        $invoice->delete();

        Flux::toast(variant: 'success', text: __('Invoice deleted.'));
    }
}; ?>

<div class="py-6">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
        <flux:button href="{{ route('invoicing.create') }}" variant="primary" wire:navigate>
            {{ __('New Invoice') }}
        </flux:button>
    </div>

    <div class="flex items-center gap-4 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search by client or number...')" class="max-w-sm flex-1" />
        <flux:select wire:model.live="statusFilter" class="max-w-xs">
            <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
            <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
            <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
            <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
            <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
        </flux:select>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-(--radius-card) shadow-(--shadow-elevated)">
        @if ($this->invoices->isEmpty())
            <div class="p-8 text-center">
                <flux:subheading>{{ __('No invoices found.') }}</flux:subheading>
            </div>
        @else
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->invoices as $invoice)
                    <div class="flex items-center justify-between p-4">
                        <a href="{{ route('invoicing.show', $invoice) }}" wire:navigate class="flex-1">
                            <div class="flex items-center gap-3">
                                <flux:heading>{{ $invoice->invoice_number }}</flux:heading>
                                <flux:badge :color="match($invoice->status) {
                                    'paid' => 'green',
                                    'sent' => 'blue',
                                    'draft' => 'zinc',
                                    'cancelled' => 'red',
                                    default => 'zinc',
                                }">
                                    {{ __(ucfirst($invoice->status)) }}
                                </flux:badge>
                            </div>
                            <flux:subheading class="text-xs mt-1">
                                {{ $invoice->client_name }}
                                &middot;
                                ${{ number_format($invoice->total, 2) }}
                                &middot;
                                {{ __('Due') }} {{ $invoice->due_date->format('M j, Y') }}
                            </flux:subheading>
                        </a>
                        <div class="flex items-center gap-2">
                            <flux:button wire:navigate href="{{ route('invoicing.edit', $invoice) }}" variant="subtle" size="sm" icon="pencil" />
                            <flux:button wire:click="delete({{ $invoice->id }})" wire:confirm="{{ __('Are you sure?') }}" variant="subtle" size="sm" icon="trash" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
