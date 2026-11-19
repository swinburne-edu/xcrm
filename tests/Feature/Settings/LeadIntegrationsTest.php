<?php

use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Enums\LeadEventStatus;
use App\Domain\Intake\Models\LeadIntegration;
use App\Domain\Intake\Models\LeadWebhookEvent;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');

    $this->payload = fn (array $extra = []) => $extra + ['provider' => 'facebook', 'name' => ' Form tuyển sinh 2027 ', 'secret' => 'app-secret-123', 'access_token' => 'page-token-xyz'];
});

it('creates a Facebook integration with a generated verify token and encrypted secrets', function () {
    $this->actingAs($this->admin)->post('/settings/lead-integrations', ($this->payload)(['field_map' => [['name' => 'sdt_zalo', 'key' => 'phone'], ['name' => '', 'key' => 'email']]]))
        ->assertSessionHasNoErrors();

    $integration = LeadIntegration::query()->firstOrFail();
    expect($integration->name)->toBe('Form tuyển sinh 2027')->and($integration->provider->value)->toBe('facebook')->and($integration->organization_id)->toBe($this->org->id)
        ->and($integration->secret)->toBe('app-secret-123')->and(strlen($integration->verify_token))->toBe(32)->and($integration->field_map)->toBe(['sdt_zalo' => 'phone'])
        ->and($integration->uuid)->not->toBeEmpty()->and($integration->is_active)->toBeTrue();
    expect(DB::table('lead_integrations')->value('secret'))->not->toContain('app-secret-123');
});

it('needs a secret and a known provider to create one', function () {
    $this->actingAs($this->admin);

    $this->post('/settings/lead-integrations', ($this->payload)(['secret' => '']))->assertSessionHasErrors('secret');
    $this->post('/settings/lead-integrations', ($this->payload)(['provider' => 'zalo']))->assertSessionHasErrors('provider');
    $this->post('/settings/lead-integrations', ($this->payload)(['name' => '']))->assertSessionHasErrors('name');
    $this->post('/settings/lead-integrations', ($this->payload)(['field_map' => [['name' => 'x', 'key' => 'khong-co']]]))->assertSessionHasErrors('field_map.0.key');
    expect(LeadIntegration::query()->count())->toBe(0);
});

it('keeps the saved secrets when an edit leaves them blank, and replaces them when given', function () {
    $integration = LeadIntegration::factory()->create(['organization_id' => $this->org->id, 'secret' => 'old-secret', 'access_token' => 'old-token']);

    $this->actingAs($this->admin)->put("/settings/lead-integrations/{$integration->id}", ['name' => 'Tên mới', 'secret' => '', 'access_token' => '', 'is_active' => false])->assertSessionHasNoErrors();
    $integration->refresh();
    expect($integration->name)->toBe('Tên mới')->and($integration->secret)->toBe('old-secret')->and($integration->access_token)->toBe('old-token')->and($integration->is_active)->toBeFalse();

    $this->put("/settings/lead-integrations/{$integration->id}", ['name' => 'Tên mới', 'secret' => 'new-secret']);
    expect($integration->fresh()->secret)->toBe('new-secret');
});

it('does not let the platform change after creation', function () {
    $integration = LeadIntegration::factory()->create(['organization_id' => $this->org->id]);

    $this->actingAs($this->admin)->put("/settings/lead-integrations/{$integration->id}", ['name' => 'x', 'provider' => 'tiktok'])->assertSessionHasErrors('provider');
});

it('lists integrations without secrets, with the webhook url, counts and the latest leads', function () {
    $integration = LeadIntegration::factory()->create(['organization_id' => $this->org->id, 'secret' => 'top-secret', 'access_token' => 'tok', 'verify_token' => 'vt-123']);
    LeadWebhookEvent::query()->create(['integration_id' => $integration->id, 'external_id' => '1', 'status' => LeadEventStatus::Created]);
    LeadWebhookEvent::query()->create(['integration_id' => $integration->id, 'external_id' => '2', 'status' => LeadEventStatus::Failed, 'error' => 'Thiếu số điện thoại.']);

    $response = $this->actingAs($this->admin)->get('/settings/lead-integrations');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('LeadIntegrations/Index')
        ->has('integrations', 1)->where('integrations.0.webhook_url', url("/api/webhooks/leads/{$integration->uuid}"))
        ->where('integrations.0.has_secret', true)->where('integrations.0.verify_token', 'vt-123')
        ->where('integrations.0.counts.created', 1)->where('integrations.0.counts.failed', 1)->has('integrations.0.events', 2)
        ->where('can.manage', true));
    expect($response->getContent())->not->toContain('top-secret')->not->toContain('&quot;tok&quot;');
});

it('shows only integrations of the own organization', function () {
    LeadIntegration::factory()->create(['organization_id' => $this->org->id, 'name' => 'Của tôi']);
    LeadIntegration::factory()->create(['name' => 'Tổ chức khác']);

    $this->actingAs($this->admin)->get('/settings/lead-integrations')->assertInertia(fn (Assert $page) => $page->has('integrations', 1)->where('integrations.0.name', 'Của tôi'));
});

it('limits campaign and source to the organization', function () {
    $foreign = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($foreign);
    $foreignSource = LeadSource::query()->withoutGlobalScopes()->where('organization_id', $foreign->id)->value('id');

    $this->actingAs($this->admin)->post('/settings/lead-integrations', ($this->payload)(['source_id' => $foreignSource]))->assertSessionHasErrors('source_id');
});

it('lets viewers list but not change, and hides the verify token from them', function () {
    $this->counselor->givePermissionTo('lead_integration.view');
    $integration = LeadIntegration::factory()->create(['organization_id' => $this->org->id, 'verify_token' => 'vt-123']);

    $this->actingAs($this->counselor)->get('/settings/lead-integrations')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.manage', false)->where('integrations.0.verify_token', null));
    $this->post('/settings/lead-integrations', ($this->payload)())->assertForbidden();
    $this->put("/settings/lead-integrations/{$integration->id}", ['name' => 'x'])->assertForbidden();
    $this->delete("/settings/lead-integrations/{$integration->id}")->assertForbidden();
});

it('forbids users without the view permission', function () {
    $this->actingAs($this->counselor)->get('/settings/lead-integrations')->assertForbidden();
});

it('deletes an integration, whose webhook then answers 404', function () {
    $integration = LeadIntegration::factory()->create(['organization_id' => $this->org->id]);

    $this->actingAs($this->admin)->delete("/settings/lead-integrations/{$integration->id}")->assertSessionHasNoErrors();

    expect(LeadIntegration::query()->count())->toBe(0);
    $this->postJson("/api/webhooks/leads/{$integration->uuid}", [])->assertNotFound();
});

it('requires login', function () {
    $this->get('/settings/lead-integrations')->assertRedirect(route('login'));
});
