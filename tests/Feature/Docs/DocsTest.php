<?php

use App\Models\Document;
use App\Models\Spoke;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $docs = Spoke::factory()->create(['slug' => 'docs', 'is_active' => true]);
    $this->tenant->grantSpoke($docs);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant, ['joined_at' => now()]);

    app()->instance('current.tenant', $this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
});

test('guest cannot access docs spoke', function () {
    $this->get(route('docs.index'))->assertRedirect(route('login'));
});

test('user with no tenant context cannot access docs spoke', function () {
    app()->instance('current.tenant', null);

    $this->actingAs($this->user)
        ->get(route('docs.index'))
        ->assertForbidden();
});

test('documents index lists tenant documents', function () {
    Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'title' => 'Getting Started',
    ]);

    $this->actingAs($this->user)
        ->get(route('docs.index'))
        ->assertOk()
        ->assertSee('Getting Started');
});

test('documents index does not show other tenant documents', function () {
    $otherTenant = Tenant::factory()->create();
    Document::factory()->create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Secret Doc',
    ]);

    Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'My Doc',
    ]);

    $this->actingAs($this->user)
        ->get(route('docs.index'))
        ->assertOk()
        ->assertSee('My Doc')
        ->assertDontSee('Secret Doc');
});

test('documents index shows empty state when no documents', function () {
    $this->actingAs($this->user)
        ->get(route('docs.index'))
        ->assertOk()
        ->assertSee(__('No documents found.'));
});

test('user can create a document', function () {
    $this->actingAs($this->user);

    Livewire::test('docs.create')
        ->set('title', 'My First Doc')
        ->set('slug', 'my-first-doc')
        ->set('content', '# Hello World')
        ->call('save')
        ->assertRedirect(route('docs.index'));

    $this->assertDatabaseHas('documents', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'title' => 'My First Doc',
        'slug' => 'my-first-doc',
        'content' => '# Hello World',
    ]);
});

test('document slug auto-generates from title', function () {
    $this->actingAs($this->user);

    $component = Livewire::test('docs.create');
    $component->set('title', 'My Document Title');

    expect($component->get('slug'))->toBe('my-document-title');

    $component->call('save');

    $this->assertDatabaseHas('documents', [
        'title' => 'My Document Title',
        'slug' => 'my-document-title',
    ]);
});

test('document shows rendered markdown', function () {
    $doc = Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Markdown Doc',
        'slug' => 'markdown-doc',
        'content' => "## Heading\n\nSome **bold** text.",
    ]);

    $this->actingAs($this->user)
        ->get(route('docs.show', $doc->slug))
        ->assertOk()
        ->assertSee('Markdown Doc')
        ->assertSee('<h2>Heading</h2>', false)
        ->assertSee('<strong>bold</strong>', false);
});

test('user can edit a document', function () {
    $doc = Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Old Title',
        'slug' => 'old-title',
    ]);

    $this->actingAs($this->user);

    Livewire::test('docs.edit', ['document' => $doc->slug])
        ->assertSet('title', 'Old Title')
        ->set('title', 'New Title')
        ->set('slug', 'new-title')
        ->call('update')
        ->assertRedirect(route('docs.index'));

    $this->assertDatabaseHas('documents', [
        'id' => $doc->id,
        'title' => 'New Title',
        'slug' => 'new-title',
    ]);
});

test('user cannot access document from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    Document::factory()->create([
        'tenant_id' => $otherTenant->id,
        'slug' => 'secret-doc',
    ]);

    $this->actingAs($this->user)
        ->get(route('docs.show', 'secret-doc'))
        ->assertNotFound();
});

test('user can delete a document from the index', function () {
    $doc = Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user);

    Livewire::test('docs.index')
        ->call('delete', $doc->id);

    $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
});

test('user can delete a document from the edit page', function () {
    $doc = Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user);

    Livewire::test('docs.edit', ['document' => $doc->slug])
        ->call('delete')
        ->assertRedirect(route('docs.index'));

    $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
});

test('document content is optional when creating', function () {
    $this->actingAs($this->user);

    Livewire::test('docs.create')
        ->set('title', 'Empty Doc')
        ->set('slug', 'empty-doc')
        ->set('content', '')
        ->call('save');

    $this->assertDatabaseHas('documents', [
        'title' => 'Empty Doc',
        'content' => null,
    ]);
});

test('document slug must be unique per tenant', function () {
    Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'slug' => 'my-slug',
    ]);

    $this->actingAs($this->user);

    Livewire::test('docs.create')
        ->set('title', 'Another Doc')
        ->set('slug', 'my-slug')
        ->call('save')
        ->assertHasErrors(['slug']);
});

test('index search filters documents by title', function () {
    Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Laravel Guide',
    ]);
    Document::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Vue Tutorial',
    ]);

    $this->actingAs($this->user);

    Livewire::test('docs.index')
        ->set('search', 'Laravel')
        ->assertSee('Laravel Guide')
        ->assertDontSee('Vue Tutorial');
});
