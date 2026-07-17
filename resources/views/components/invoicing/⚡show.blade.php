<?php

use App\Models\Invoice;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('View Invoice')] class extends Component {
    public Invoice $inv;

    public Tenant $tenant;

    public function mount(string $invoice): void
    {
        $this->tenant = app('current.tenant');

        $this->inv = Invoice::query()
            ->forTenant($this->tenant)
            ->findOrFail($invoice);
    }
}; ?>

<div class="py-6 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <flux:button wire:navigate href="{{ route('invoicing.index') }}" variant="subtle" icon="arrow-left" size="sm">
            {{ __('Back to Invoices') }}
        </flux:button>

        <div class="flex items-center gap-2">
            <flux:badge :color="match($inv->status) {
                'paid' => 'green',
                'sent' => 'blue',
                'draft' => 'zinc',
                'cancelled' => 'red',
                default => 'zinc',
            }">
                {{ __(ucfirst($inv->status)) }}
            </flux:badge>
            <flux:button wire:navigate href="{{ route('invoicing.edit', $inv) }}" variant="primary" size="sm" icon="pencil">
                {{ __('Edit') }}
            </flux:button>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-(--radius-card) shadow-(--shadow-elevated) p-6 space-y-6">
        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="xl">{{ $inv->invoice_number }}</flux:heading>
            </div>
            <div class="text-right">
                <flux:subheading>{{ __('Issue Date') }}</flux:subheading>
                <flux:subheading>{{ $inv->issue_date->format('M j, Y') }}</flux:subheading>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
            <div>
                <flux:heading size="sm" class="mb-1">{{ __('Bill To') }}</flux:heading>
                <flux:subheading>{{ $inv->client_name }}</flux:subheading>
                @if ($inv->client_email)
                    <flux:subheading>{{ $inv->client_email }}</flux:subheading>
                @endif
            </div>
            <div class="text-right">
                <flux:heading size="sm" class="mb-1">{{ __('Due Date') }}</flux:heading>
                <flux:subheading>{{ $inv->due_date->format('M j, Y') }}</flux:subheading>
            </div>
        </div>

        @if ($inv->items)
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left">
                        <th class="pb-2 pr-2">{{ __('Description') }}</th>
                        <th class="pb-2 px-2 text-right">{{ __('Qty') }}</th>
                        <th class="pb-2 px-2 text-right">{{ __('Unit Price') }}</th>
                        <th class="pb-2 pl-2 text-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inv->items as $item)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="py-2 pr-2">{{ $item['description'] }}</td>
                            <td class="py-2 px-2 text-right">{{ $item['quantity'] }}</td>
                            <td class="py-2 px-2 text-right">${{ number_format($item['unit_price'], 2) }}</td>
                            <td class="py-2 pl-2 text-right">${{ number_format($item['quantity'] * $item['unit_price'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="w-64 ml-auto space-y-1">
                <div class="flex justify-between">
                    <flux:subheading>{{ __('Subtotal') }}</flux:subheading>
                    <flux:subheading>${{ number_format($inv->subtotal, 2) }}</flux:subheading>
                </div>
                @if ($inv->tax_rate > 0)
                    <div class="flex justify-between">
                        <flux:subheading>{{ __('Tax') }} ({{ $inv->tax_rate }}%)</flux:subheading>
                        <flux:subheading>${{ number_format($inv->total - $inv->subtotal, 2) }}</flux:subheading>
                    </div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 dark:border-zinc-700 pt-1">
                    <flux:heading size="sm">{{ __('Total') }}</flux:heading>
                    <flux:heading size="sm">${{ number_format($inv->total, 2) }}</flux:heading>
                </div>
            </div>
        @endif

        @if ($inv->notes)
            <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                <flux:heading size="sm" class="mb-1">{{ __('Notes') }}</flux:heading>
                <flux:subheading>{{ $inv->notes }}</flux:subheading>
            </div>
        @endif
    </div>
</div>
