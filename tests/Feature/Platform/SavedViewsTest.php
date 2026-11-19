<?php

use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Platform\Models\SavedView;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
    $this->other = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->other->assignRole('counselor');
    $this->faculty = User::factory()->for($this->org)->create();
    $this->faculty->assignRole('faculty_viewer');

    $this->payload = fn (array $extra = []) => $extra + [
        'object' => 'applicants', 'name' => ' Lớp 12 Hà Nội ', 'view_type' => 'table',
        'config' => ['filters' => ['province_id' => ['1', '2'], 'q' => 'an'], 'sort' => '-created_at', 'per_page' => 50, 'columns' => ['phone' => false, 'code' => true]],
    ];
    $this->mine = fn (array $extra = []) => SavedView::factory()->create($extra + ['organization_id' => $this->org->id, 'user_id' => $this->counselor->id]);
});

it('saves a personal view with its cleaned config', function () {
    $this->actingAs($this->counselor)->post('/saved-views', ($this->payload)())->assertSessionHasNoErrors();

    $view = SavedView::query()->firstOrFail();
    expect($view->name)->toBe('Lớp 12 Hà Nội')->and($view->user_id)->toBe($this->counselor->id)->and($view->is_shared)->toBeFalse()
        ->and($view->object->value)->toBe('applicants')->and($view->view_type)->toBe('table')->and($view->created_by)->toBe($this->counselor->id)
        ->and($view->organization_id)->toBe($this->org->id)
        ->and($view->config)->toBe(['filters' => ['province_id' => ['1', '2'], 'q' => 'an'], 'sort' => '-created_at', 'per_page' => 50, 'columns' => ['phone' => false, 'code' => true]]);
});

it('keeps only the known parts of a config', function () {
    $this->actingAs($this->counselor)->post('/saved-views', ($this->payload)(['config' => [
        'filters' => ['ok' => 'x', 'bad key!' => 'y', 'range' => ['from' => '2026-01-01', 'to' => 'khong', 'evil' => 'z'], 'flag' => true, 'empty' => '', 'none' => []],
        'sort' => 'DROP TABLE', 'per_page' => 7, 'columns' => ['a.b' => 1, 'bad key' => true], 'junk' => 'x',
    ]]))->assertSessionHasNoErrors();

    expect(SavedView::query()->firstOrFail()->config)->toBe(['filters' => ['ok' => 'x', 'range' => ['from' => '2026-01-01'], 'flag' => true], 'sort' => null, 'per_page' => null, 'columns' => ['a.b' => true]]);
});

it('validates the name, the object and the view type', function () {
    $this->actingAs($this->counselor);

    $this->post('/saved-views', ($this->payload)(['name' => '']))->assertSessionHasErrors('name');
    $this->post('/saved-views', ($this->payload)(['object' => 'khac']))->assertStatus(422);
    $this->post('/saved-views', ($this->payload)(['view_type' => 'kanban']))->assertSessionHasErrors('view_type');
    $this->post('/saved-views', ($this->payload)(['object' => 'admission_cases', 'view_type' => 'kanban']))->assertSessionHasNoErrors();
    expect(SavedView::query()->count())->toBe(1);
});

it('needs permission to open the list the view belongs to', function () {
    $this->actingAs($this->faculty)->post('/saved-views', ($this->payload)())->assertForbidden();
    expect(SavedView::query()->count())->toBe(0);
});

it('lets only saved_view.manage share a view with the organization', function () {
    $this->actingAs($this->counselor)->post('/saved-views', ($this->payload)(['is_shared' => true]))->assertForbidden();

    $this->actingAs($this->admin)->post('/saved-views', ($this->payload)(['is_shared' => true]))->assertSessionHasNoErrors();

    $view = SavedView::query()->firstOrFail();
    expect($view->is_shared)->toBeTrue()->and($view->user_id)->toBeNull()->and($view->created_by)->toBe($this->admin->id);
});

it('shows each screen the shared views and the user\'s own, never someone else\'s', function () {
    ($this->mine)(['name' => 'Của tôi']);
    ($this->mine)(['name' => 'Của người khác', 'user_id' => $this->other->id]);
    SavedView::factory()->create(['organization_id' => $this->org->id, 'name' => 'Chung', 'is_shared' => true, 'user_id' => null]);
    SavedView::factory()->create(['organization_id' => $this->org->id, 'name' => 'Trường', 'object' => 'high_schools', 'user_id' => $this->counselor->id]);
    $foreign = Organization::factory()->create();
    SavedView::factory()->create(['organization_id' => $foreign->id, 'name' => 'Tổ chức khác', 'is_shared' => true]);

    $this->actingAs($this->counselor)->get('/applicants')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('saved_views.items', 2)->where('saved_views.items.0.name', 'Chung')->where('saved_views.items.0.can_edit', false)
        ->where('saved_views.items.1.name', 'Của tôi')->where('saved_views.items.1.can_edit', true)
        ->where('saved_views.object', 'applicants')->where('saved_views.paths', ['table' => '/applicants'])->where('saved_views.can_share', false));

    $this->actingAs($this->counselor)->get('/high-schools')->assertOk()->assertInertia(fn (Assert $page) => $page->has('saved_views.items', 1)->where('saved_views.items.0.name', 'Trường'));
    $this->actingAs($this->admin)->get('/applicants')->assertOk()->assertInertia(fn (Assert $page) => $page->has('saved_views.items', 1)->where('saved_views.can_share', true)->where('saved_views.items.0.can_edit', true));
});

it('hands the cases screen both of its boards', function () {
    $this->actingAs($this->counselor)->get('/cases/table')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('saved_views.object', 'admission_cases')->where('saved_views.paths', ['kanban' => '/cases', 'table' => '/cases/table']));
});

it('lets the owner rename a view and keeps its settings', function () {
    $view = ($this->mine)(['name' => 'Cũ', 'config' => ['filters' => ['q' => 'x'], 'sort' => null, 'per_page' => null, 'columns' => null]]);

    $this->actingAs($this->counselor)->put("/saved-views/{$view->id}", ['name' => 'Mới'])->assertSessionHasNoErrors();

    $view->refresh();
    expect($view->name)->toBe('Mới')->and($view->config['filters'])->toBe(['q' => 'x'])->and($view->user_id)->toBe($this->counselor->id);
});

it('overwrites the config when the screen sends a new one', function () {
    $view = ($this->mine)();

    $this->actingAs($this->counselor)->put("/saved-views/{$view->id}", ['name' => $view->name, 'view_type' => 'table', 'config' => ['filters' => ['q' => 'moi'], 'sort' => 'full_name', 'per_page' => 100, 'columns' => null]])
        ->assertSessionHasNoErrors();

    expect($view->fresh()->config)->toBe(['filters' => ['q' => 'moi'], 'sort' => 'full_name', 'per_page' => 100, 'columns' => null]);
});

it('hides another user\'s personal view completely', function () {
    $view = ($this->mine)(['user_id' => $this->other->id]);

    $this->actingAs($this->counselor)->put("/saved-views/{$view->id}", ['name' => 'Hack'])->assertNotFound();
    $this->actingAs($this->counselor)->delete("/saved-views/{$view->id}")->assertNotFound();
    $this->actingAs($this->admin)->delete("/saved-views/{$view->id}")->assertNotFound();
    expect($view->fresh()->name)->not->toBe('Hack')->and(SavedView::query()->count())->toBe(1);
});

it('lets only saved_view.manage edit, delete or unshare a shared view', function () {
    $view = SavedView::factory()->create(['organization_id' => $this->org->id, 'is_shared' => true, 'user_id' => null, 'name' => 'Chung']);

    $this->actingAs($this->counselor)->put("/saved-views/{$view->id}", ['name' => 'Hack'])->assertForbidden();
    $this->actingAs($this->counselor)->delete("/saved-views/{$view->id}")->assertForbidden();

    $this->actingAs($this->admin)->put("/saved-views/{$view->id}", ['name' => 'Chung mới', 'is_shared' => true])->assertSessionHasNoErrors();
    expect($view->fresh()->name)->toBe('Chung mới');

    $this->actingAs($this->admin)->put("/saved-views/{$view->id}", ['name' => 'Riêng của admin', 'is_shared' => false])->assertSessionHasNoErrors();
    expect($view->fresh()->is_shared)->toBeFalse()->and($view->fresh()->user_id)->toBe($this->admin->id);
});

it('does not let a member share their own view without saved_view.manage', function () {
    $view = ($this->mine)();

    $this->actingAs($this->counselor)->put("/saved-views/{$view->id}", ['name' => 'x', 'is_shared' => true])->assertForbidden();
    expect($view->fresh()->is_shared)->toBeFalse();
});

it('deletes a view for its owner', function () {
    $view = ($this->mine)();

    $this->actingAs($this->counselor)->delete("/saved-views/{$view->id}")->assertSessionHasNoErrors();

    expect(SavedView::query()->count())->toBe(0);
});

it('never reaches a view of another organization', function () {
    $foreign = Organization::factory()->create();
    $view = SavedView::factory()->create(['organization_id' => $foreign->id, 'is_shared' => true]);

    $this->actingAs($this->admin)->put("/saved-views/{$view->id}", ['name' => 'Hack'])->assertNotFound();
    $this->actingAs($this->admin)->delete("/saved-views/{$view->id}")->assertNotFound();
});

it('requires login', function () {
    $this->post('/saved-views', ($this->payload)())->assertRedirect(route('login'));
});
