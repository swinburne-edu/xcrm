<?php

use App\Domain\Api\Jobs\DeliverWebhook;
use App\Domain\Api\Models\Webhook;
use App\Domain\Api\Models\WebhookDelivery;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.5, settings screen /settings/api: own API tokens, webhooks and their deliveries. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['workflows.resolve_dns' => false]);
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
});

it('creates a token that works, shows it once and revokes it', function () {
    $this->actingAs($this->admin)->post('/settings/api/tokens', ['name' => 'LMS', 'write' => true, 'expires_in_days' => 30])->assertRedirect();
    $stored = $this->admin->tokens()->firstOrFail();
    expect($stored->abilities)->toBe(['read', 'write'])->and($stored->expires_at)->not->toBeNull();

    $first = $this->actingAs($this->admin)->get('/settings/api');
    $first->assertInertia(fn (Assert $p) => $p->component('ApiAccess/Index')->has('tokens', 1)->where('tokens.0.name', 'LMS')->has('new_token'));
    $plain = $first->viewData('page')['props']['new_token'];
    expect($plain)->toContain('|');

    $again = $this->actingAs($this->admin)->get('/settings/api');
    $again->assertInertia(fn (Assert $p) => $p->where('new_token', null));
    expect($again->getContent())->not->toContain($plain);

    $this->actingAs($this->admin)->delete("/settings/api/tokens/{$stored->id}")->assertRedirect();
    expect($this->admin->tokens()->count())->toBe(0);
    $this->actingAs($this->admin)->post('/settings/api/tokens', ['name' => ''])->assertSessionHasErrors('name');
});

it('creates webhooks only for safe addresses, shows the secret once, renews and deletes', function () {
    $payload = ['name' => 'LMS', 'url' => 'https://lms.example.com/hook', 'events' => ['case.enrolled'], 'is_active' => true];

    $this->actingAs($this->admin)->post('/settings/api/webhooks', ['url' => 'http://lms.example.com'] + $payload)->assertSessionHasErrors('url');
    $this->actingAs($this->admin)->post('/settings/api/webhooks', ['url' => 'https://127.0.0.1/x'] + $payload)->assertSessionHasErrors('url');
    $this->actingAs($this->admin)->post('/settings/api/webhooks', ['events' => ['nope']] + $payload)->assertSessionHasErrors('events.0');

    $this->actingAs($this->admin)->post('/settings/api/webhooks', $payload)->assertRedirect();
    $hook = Webhook::query()->firstOrFail();
    $secret = $hook->secret;
    expect($secret)->toHaveLength(40);

    $page = $this->actingAs($this->admin)->get('/settings/api');
    $page->assertInertia(fn (Assert $p) => $p->has('webhooks', 1)->where('new_secret', $secret));
    $again = $this->actingAs($this->admin)->get('/settings/api');
    expect($again->getContent())->not->toContain($secret);

    $this->actingAs($this->admin)->put("/settings/api/webhooks/{$hook->id}", ['is_active' => false])->assertRedirect();
    expect($hook->fresh()->is_active)->toBeFalse();
    $this->actingAs($this->admin)->put("/settings/api/webhooks/{$hook->id}", $payload + ['renew_secret' => true])->assertRedirect();
    expect($hook->fresh()->secret)->not->toBe($secret);

    $this->actingAs($this->admin)->delete("/settings/api/webhooks/{$hook->id}")->assertRedirect();
    expect(Webhook::query()->count())->toBe(0);
});

it('queues a manual re-send of a failed delivery', function () {
    Queue::fake();
    $hook = Webhook::query()->create(['organization_id' => $this->org->id, 'name' => 'LMS', 'url' => 'https://lms.example.com/hook', 'events' => ['case.enrolled'], 'secret' => 's']);
    $delivery = WebhookDelivery::query()->create(['organization_id' => $this->org->id, 'webhook_id' => $hook->id, 'event' => 'case.enrolled', 'payload' => ['x' => 1], 'status' => 'failed', 'attempts' => 4, 'error' => 'boom']);

    $this->actingAs($this->admin)->post("/settings/api/webhooks/{$hook->id}/deliveries/{$delivery->id}/redeliver")->assertRedirect();

    Queue::assertPushed(DeliverWebhook::class);
    expect($delivery->refresh()->status)->toBe('pending')->and($delivery->attempts)->toBe(0);
});

it('is closed to people without the permissions', function () {
    $this->actingAs($this->counselor)->get('/settings/api')->assertForbidden();
    $this->actingAs($this->counselor)->post('/settings/api/tokens', ['name' => 'x'])->assertForbidden();
    $this->actingAs($this->counselor)->post('/settings/api/webhooks', ['name' => 'x', 'url' => 'https://a.example.com', 'events' => ['case.enrolled']])->assertForbidden();
    expect(Http::recorded())->toHaveCount(0);
});
