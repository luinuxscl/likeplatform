<?php

use App\Models\Invoice;
use App\Models\Spoke;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $invoicing = Spoke::factory()->create(['slug' => 'invoicing', 'is_active' => true]);
    $this->tenant->grantSpoke($invoicing);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant, ['joined_at' => now()]);

    app()->instance('current.tenant', $this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
});

test('guest cannot access invoicing spoke', function () {
    $this->get(route('invoicing.index'))->assertRedirect(route('login'));
});

test('user with no tenant context cannot access invoicing spoke', function () {
    app()->instance('current.tenant', null);

    $this->actingAs($this->user)
        ->get(route('invoicing.index'))
        ->assertForbidden();
});

test('invoices index lists tenant invoices', function () {
    $inv = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'client_name' => 'ACME Corp',
    ]);

    $this->actingAs($this->user)
        ->get(route('invoicing.index'))
        ->assertOk()
        ->assertSee('ACME Corp');
});

test('invoices index does not show other tenant invoices', function () {
    $otherTenant = Tenant::factory()->create();
    Invoice::factory()->create([
        'tenant_id' => $otherTenant->id,
        'client_name' => 'Secret Client',
    ]);

    Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'client_name' => 'My Client',
    ]);

    $this->actingAs($this->user)
        ->get(route('invoicing.index'))
        ->assertOk()
        ->assertSee('My Client')
        ->assertDontSee('Secret Client');
});

test('index can filter by status', function () {
    Invoice::factory()->draft()->create(['tenant_id' => $this->tenant->id, 'client_name' => 'Draft Client']);
    Invoice::factory()->paid()->create(['tenant_id' => $this->tenant->id, 'client_name' => 'Paid Client']);

    $this->actingAs($this->user);

    Livewire::test('invoicing.index')
        ->assertSee('Draft Client')
        ->assertSee('Paid Client');

    Livewire::test('invoicing.index')
        ->set('statusFilter', 'paid')
        ->assertSee('Paid Client')
        ->assertDontSee('Draft Client');
});

test('index search filters by client name or invoice number', function () {
    Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'client_name' => 'Alpha Inc',
        'invoice_number' => 'INV-2026-0042',
    ]);
    Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'client_name' => 'Beta LLC',
        'invoice_number' => 'INV-2026-0099',
    ]);

    $this->actingAs($this->user);

    Livewire::test('invoicing.index')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Inc')
        ->assertDontSee('Beta LLC');

    Livewire::test('invoicing.index')
        ->set('search', '0042')
        ->assertSee('Alpha Inc')
        ->assertDontSee('Beta LLC');
});

test('user can create an invoice with items', function () {
    $this->actingAs($this->user);

    Livewire::test('invoicing.create')
        ->set('client_name', 'New Client')
        ->set('client_email', 'client@example.com')
        ->set('issue_date', '2026-07-01')
        ->set('due_date', '2026-08-01')
        ->set('tax_rate', '16')
        ->set('items', [
            ['description' => 'Web Design', 'quantity' => 1, 'unit_price' => 1000],
            ['description' => 'Hosting', 'quantity' => 12, 'unit_price' => 25],
        ])
        ->call('save')
        ->assertRedirect(route('invoicing.index'));

    $this->assertDatabaseHas('invoices', [
        'tenant_id' => $this->tenant->id,
        'client_name' => 'New Client',
        'client_email' => 'client@example.com',
        'tax_rate' => 16,
        'status' => 'draft',
    ]);

    $invoice = Invoice::where('client_name', 'New Client')->firstOrFail();
    expect($invoice->subtotal)->toBe('1300.00');
    expect($invoice->total)->toBe('1508.00');
    expect($invoice->items)->toHaveCount(2);
});

test('invoice number is auto-generated incrementally', function () {
    $this->actingAs($this->user);

    Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_number' => 'INV-2026-0001',
    ]);

    Livewire::test('invoicing.create')
        ->set('client_name', 'Another Client')
        ->set('items', [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10]])
        ->call('save');

    $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-2026-0002']);
});

test('invoice shows details with items table', function () {
    $inv = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'client_name' => 'View Client',
        'invoice_number' => 'INV-2026-0100',
        'items' => [
            ['description' => 'Consulting', 'quantity' => 5, 'unit_price' => 200],
        ],
        'subtotal' => 1000,
        'tax_rate' => 10,
        'total' => 1100,
    ]);

    $this->actingAs($this->user)
        ->get(route('invoicing.show', $inv))
        ->assertOk()
        ->assertSee('INV-2026-0100')
        ->assertSee('View Client')
        ->assertSee('Consulting')
        ->assertSee('$1,000.00')
        ->assertSee('$1,100.00');
});

test('user can edit an invoice', function () {
    $inv = Invoice::factory()->draft()->create([
        'tenant_id' => $this->tenant->id,
        'client_name' => 'Old Name',
        'items' => [
            ['description' => 'Old Item', 'quantity' => 1, 'unit_price' => 50],
        ],
        'subtotal' => 50,
        'tax_rate' => 0,
        'total' => 50,
    ]);

    $this->actingAs($this->user);

    Livewire::test('invoicing.edit', ['invoice' => (string) $inv->id])
        ->set('client_name', 'New Name')
        ->set('items', [
            ['description' => 'New Item', 'quantity' => 2, 'unit_price' => 100],
        ])
        ->call('update')
        ->assertRedirect(route('invoicing.index'));

    $this->assertDatabaseHas('invoices', [
        'id' => $inv->id,
        'client_name' => 'New Name',
        'subtotal' => 200,
        'total' => 200,
    ]);
});

test('user can mark invoice as sent or paid', function () {
    $inv = Invoice::factory()->draft()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->user);

    Livewire::test('invoicing.edit', ['invoice' => (string) $inv->id])
        ->call('markAs', 'sent');

    expect($inv->fresh()->status)->toBe('sent');

    Livewire::test('invoicing.edit', ['invoice' => (string) $inv->id])
        ->call('markAs', 'paid');

    expect($inv->fresh()->status)->toBe('paid');
});

test('user can delete an invoice from index', function () {
    $inv = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->user);

    Livewire::test('invoicing.index')
        ->call('delete', $inv->id);

    $this->assertDatabaseMissing('invoices', ['id' => $inv->id]);
});

test('user can delete an invoice from edit page', function () {
    $inv = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->user);

    Livewire::test('invoicing.edit', ['invoice' => (string) $inv->id])
        ->call('delete')
        ->assertRedirect(route('invoicing.index'));

    $this->assertDatabaseMissing('invoices', ['id' => $inv->id]);
});

test('cannot access invoice from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $inv = Invoice::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('invoicing.show', $inv))
        ->assertNotFound();
});

test('invoice requires at least one item', function () {
    $this->actingAs($this->user);

    Livewire::test('invoicing.create')
        ->set('items', [])
        ->call('save')
        ->assertHasErrors(['items']);
});

test('item description is required', function () {
    $this->actingAs($this->user);

    Livewire::test('invoicing.create')
        ->set('client_name', 'Test')
        ->set('items', [
            ['description' => '', 'quantity' => 1, 'unit_price' => 10],
        ])
        ->call('save')
        ->assertHasErrors(['items.0.description']);
});
