<?php

use App\Models\Invoice;
use App\Models\Tenant;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Edit Invoice')] class extends Component {
    public Invoice $inv;

    public string $client_name = '';

    public string $client_email = '';

    public string $issue_date = '';

    public string $due_date = '';

    /** @var array<int, array{description: string, quantity: int, unit_price: float}> */
    public array $items = [];

    public string $notes = '';

    public int $tax_rate = 0;

    public Tenant $tenant;

    public function mount(string $invoice): void
    {
        $this->tenant = app('current.tenant');

        $this->inv = Invoice::query()
            ->forTenant($this->tenant)
            ->findOrFail($invoice);

        $this->client_name = $this->inv->client_name;
        $this->client_email = $this->inv->client_email ?? '';
        $this->issue_date = $this->inv->issue_date->format('Y-m-d');
        $this->due_date = $this->inv->due_date->format('Y-m-d');
        $this->items = $this->inv->items ?? [['description' => '', 'quantity' => 1, 'unit_price' => 0.00]];
        $this->notes = $this->inv->notes ?? '';
        $this->tax_rate = (int) $this->inv->tax_rate;
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'quantity' => 1, 'unit_price' => 0.00];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function update(): void
    {
        $validated = $this->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'tax_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = array_sum(array_map(fn ($item) => $item['quantity'] * $item['unit_price'], $validated['items']));
        $total = round($subtotal * (1 + $validated['tax_rate'] / 100), 2);

        $this->inv->update([
            ...$validated,
            'subtotal' => $subtotal,
            'total' => $total,
        ]);

        Flux::toast(variant: 'success', text: __('Invoice updated.'));

        $this->redirect(route('invoicing.index'));
    }

    public function markAs(string $status): void
    {
        $this->inv->update(['status' => $status]);

        Flux::toast(variant: 'success', text: __('Invoice marked as '.$status.'.'));

        $this->redirect(route('invoicing.index'));
    }

    public function delete(): void
    {
        $this->inv->delete();

        Flux::toast(variant: 'success', text: __('Invoice deleted.'));

        $this->redirect(route('invoicing.index'));
    }
}; ?>

<div class="py-6 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Edit Invoice') }}</flux:heading>

        <div class="flex items-center gap-2">
            <flux:button wire:click="delete" wire:confirm="{{ __('Are you sure?') }}" variant="danger" size="sm" icon="trash" />
        </div>
    </div>

    <form wire:submit="update" class="space-y-6">
        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('Invoice Number') }}</flux:label>
                <flux:input :value="$inv->invoice_number" disabled />
            </flux:field>

            <div class="flex items-end gap-2">
                @foreach (['draft', 'sent', 'paid'] as $status)
                    <flux:button wire:click="markAs('{{ $status }}')" variant="{{ $inv->status === $status ? 'primary' : 'subtle' }}" size="sm">
                        {{ __(ucfirst($status)) }}
                    </flux:button>
                @endforeach
            </div>

            <flux:field>
                <flux:label>{{ __('Client Name') }}</flux:label>
                <flux:input wire:model="client_name" />
                <flux:error name="client_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Client Email') }}</flux:label>
                <flux:input wire:model="client_email" type="email" />
                <flux:error name="client_email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Issue Date') }}</flux:label>
                <flux:input wire:model="issue_date" type="date" />
                <flux:error name="issue_date" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Due Date') }}</flux:label>
                <flux:input wire:model="due_date" type="date" />
                <flux:error name="due_date" />
            </flux:field>
        </div>

        <div class="flex items-center gap-2">
            <flux:label>{{ __('Tax Rate') }}</flux:label>
            <div class="flex items-center gap-1">
                <flux:input wire:model="tax_rate" type="number" min="0" max="100" class="w-20 text-right" />
                <flux:subheading>%</flux:subheading>
            </div>
            <flux:error name="tax_rate" />
        </div>

        <div>
            <div class="flex items-center justify-between mb-3">
                <flux:label>{{ __('Items') }}</flux:label>
                <flux:button wire:click="addItem" variant="subtle" size="sm" icon="plus">{{ __('Add Item') }}</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="flex items-start gap-3 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg" wire:key="item-{{ $index }}">
                        <div class="flex-1 grid grid-cols-12 gap-2">
                            <flux:field class="col-span-5">
                                <flux:label class="sr-only">{{ __('Description') }}</flux:label>
                                <flux:input wire:model="items.{{ $index }}.description" :placeholder="__('Description')" />
                                <flux:error name="items.{{ $index }}.description" />
                            </flux:field>
                            <flux:field class="col-span-2">
                                <flux:label class="sr-only">{{ __('Qty') }}</flux:label>
                                <flux:input wire:model.blur="items.{{ $index }}.quantity" type="number" min="1" :placeholder="__('Qty')" />
                                <flux:error name="items.{{ $index }}.quantity" />
                            </flux:field>
                            <flux:field class="col-span-4">
                                <flux:label class="sr-only">{{ __('Unit Price') }}</flux:label>
                                <flux:input wire:model.blur="items.{{ $index }}.unit_price" type="number" min="0" step="0.01" :placeholder="__('Unit Price')" />
                                <flux:error name="items.{{ $index }}.unit_price" />
                            </flux:field>
                            <div class="col-span-1 flex items-center justify-end pt-6">
                                @if (count($items) > 1)
                                    <flux:button wire:click="removeItem({{ $index }})" variant="subtle" size="sm" icon="x-mark" />
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                <flux:error name="items" />
            </div>
        </div>

        <flux:field>
            <flux:label>{{ __('Notes') }}</flux:label>
            <flux:textarea wire:model="notes" rows="3" />
            <flux:error name="notes" />
        </flux:field>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            <flux:button wire:navigate href="{{ route('invoicing.show', $inv) }}">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
