<?php

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Telephony\Models\CallIntegration;
use App\Domain\Telephony\Models\CallLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.7: click-to-call, the popup feed, the call log page and the integration settings. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
    $this->applicant = Applicant::factory()->for($this->org)->create(['phone' => '0912345678']);
});

it('places a click-to-call through the log driver and records the call', function () {
    config(['telephony.driver' => 'log']);

    $this->actingAs($this->admin)->postJson('/calls/dial', ['applicant_uuid' => $this->applicant->uuid])->assertOk()->assertJson(['status' => 'dialing']);

    $log = CallLog::query()->firstOrFail();
    expect($log->direction)->toBe('out')->and($log->user_id)->toBe($this->admin->id)->and($log->applicant_id)->toBe($this->applicant->id)->and($log->to_number)->toBe('84912345678');
});

it('asks the gateway to call through the http driver and records a refusal', function () {
    config(['telephony.driver' => 'http', 'telephony.http.url' => 'https://pbx.example.com/dial', 'telephony.http.token' => 't']);
    Http::fake(['pbx.example.com/*' => Http::sequence()->push(['ok' => true], 200)->push('no', 500)]);

    $this->actingAs($this->admin)->postJson('/calls/dial', ['applicant_uuid' => $this->applicant->uuid])->assertOk();
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer t') && $r['to'] === '84912345678' && $r['agent']['id'] === $this->admin->id);

    $this->actingAs($this->admin)->postJson('/calls/dial', ['applicant_uuid' => $this->applicant->uuid])->assertStatus(502)->assertJson(['status' => 'failed']);
    expect(CallLog::query()->where('status', 'failed')->first()->error)->toContain('500');
});

it('refuses to dial without a number, without permission or outside the data scope', function () {
    $none = Applicant::factory()->for($this->org)->create(['phone' => null]);
    $this->actingAs($this->admin)->postJson('/calls/dial', ['applicant_uuid' => $none->uuid])->assertUnprocessable();

    $faculty = User::factory()->for($this->org)->create();
    $faculty->givePermissionTo('applicant.view');
    $this->actingAs($faculty)->postJson('/calls/dial', ['applicant_uuid' => $this->applicant->uuid])->assertForbidden();

    $this->actingAs($this->counselor)->postJson('/calls/dial', ['applicant_uuid' => $this->applicant->uuid])->assertNotFound();
});

it('feeds the popup with ringing inbound calls of the user, recent ones only', function () {
    $make = fn (array $a) => CallLog::query()->create(['organization_id' => $this->org->id, 'direction' => 'in', 'from_number' => '0912345678', 'status' => 'ringing', 'started_at' => now(), ...$a]);
    $make(['external_id' => 'mine', 'user_id' => $this->counselor->id, 'applicant_id' => $this->applicant->id]);
    $make(['external_id' => 'theirs', 'user_id' => $this->admin->id]);
    $old = $make(['external_id' => 'old', 'user_id' => $this->counselor->id]);
    CallLog::query()->whereKey($old->id)->update(['created_at' => now()->subMinutes(10)]);
    $make(['external_id' => 'answered', 'user_id' => $this->counselor->id, 'status' => 'answered']);

    $this->actingAs($this->counselor)->getJson('/calls/incoming')->assertOk()->assertJsonCount(1, 'calls')->assertJsonPath('calls.0.applicant_name', $this->applicant->full_name);
});

it('lists the call log and shares the telephony flag', function () {
    CallLog::query()->create(['organization_id' => $this->org->id, 'external_id' => 'a', 'direction' => 'in', 'from_number' => '0912345678', 'status' => 'missed', 'applicant_id' => $this->applicant->id]);

    $this->actingAs($this->admin)->get('/calls?filter[status]=missed')->assertInertia(fn (Assert $p) => $p->component('Calls/Index')->has('calls.data', 1)->where('telephony', true));
    $this->actingAs($this->admin)->get('/calls?filter[status]=ended')->assertInertia(fn (Assert $p) => $p->has('calls.data', 0));

    $faculty = User::factory()->for($this->org)->create();
    $this->actingAs($faculty)->get('/calls')->assertForbidden();
});

it('manages integrations and shows the secret only once', function () {
    $this->actingAs($this->admin)->post('/settings/call-integrations', ['name' => 'Tong dai'])->assertRedirect();
    $integration = CallIntegration::query()->firstOrFail();
    $secret = $integration->secret;
    expect($secret)->toHaveLength(40);

    $page = $this->actingAs($this->admin)->get('/settings/call-integrations');
    $page->assertInertia(fn (Assert $p) => $p->component('CallIntegrations/Index')->has('integrations', 1)->where('integrations.0.webhook_url', $integration->webhookUrl())->where('new_secret', $secret));
    $again = $this->actingAs($this->admin)->get('/settings/call-integrations');
    $again->assertInertia(fn (Assert $p) => $p->where('new_secret', null));
    expect($again->getContent())->not->toContain($secret);

    $this->actingAs($this->admin)->put("/settings/call-integrations/{$integration->id}", ['is_active' => false])->assertRedirect();
    expect($integration->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->put("/settings/call-integrations/{$integration->id}", ['renew_secret' => true])->assertRedirect();
    expect($integration->fresh()->secret)->not->toBe($secret);

    $this->actingAs($this->counselor)->get('/settings/call-integrations')->assertForbidden();
    $this->actingAs($this->counselor)->post('/settings/call-integrations', ['name' => 'x'])->assertForbidden();

    $this->actingAs($this->admin)->delete("/settings/call-integrations/{$integration->id}")->assertRedirect();
    expect(CallIntegration::query()->count())->toBe(0);
});
