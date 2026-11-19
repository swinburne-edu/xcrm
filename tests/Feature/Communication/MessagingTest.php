<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Communication\Enums\MessageStatus;
use App\Domain\Communication\Jobs\DeliverMessage;
use App\Domain\Communication\Models\MessageLog;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Services\TemplateRenderer;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.1: message templates, the bulk send with consent rules, the channel drivers and the log.
 * Module under test: app/Domain/Communication and its two controllers. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Đại học Demo']);
    $this->admin = User::factory()->for($this->org)->create(['name' => 'Cô Lan', 'data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    config(['communication.drivers.zns' => 'log', 'communication.drivers.sms' => 'log', 'communication.drivers.email' => 'log']);

    $this->template = fn (array $attrs = []) => MessageTemplate::query()->create([
        'organization_id' => $this->org->id, 'channel' => 'sms', 'name' => 'Nhắc hạn', 'body' => 'Chào {{applicant.first_name}}, {{organization.name}} nhắc bạn nộp hồ sơ.', ...$attrs,
    ]);
    $this->person = fn (array $attrs = []) => Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn Văn An', 'phone' => '0912345678', 'email' => 'an@example.com', 'consent_marketing' => true, ...$attrs]);
});

it('creates, edits and deletes templates and rejects unknown variables', function () {
    $this->actingAs($this->admin)->post('/settings/message-templates', ['channel' => 'sms', 'name' => 'A', 'body' => 'Xin chào {{applicant.full_name}}'])->assertRedirect();
    $t = MessageTemplate::query()->firstOrFail();

    $this->actingAs($this->admin)->put("/settings/message-templates/{$t->id}", ['channel' => 'sms', 'name' => 'B', 'body' => 'Hi'])->assertRedirect();
    expect($t->fresh()->name)->toBe('B');

    $this->actingAs($this->admin)->post('/settings/message-templates', ['channel' => 'sms', 'name' => 'X', 'body' => 'Chào {{applicant.nope}}'])->assertSessionHasErrors('body');
    $this->actingAs($this->admin)->post('/settings/message-templates', ['channel' => 'zns', 'name' => 'Z', 'body' => 'Hi'])->assertSessionHasErrors('provider_template_id');
    $this->actingAs($this->admin)->post('/settings/message-templates', ['channel' => 'email', 'name' => 'E', 'body' => 'Hi'])->assertSessionHasErrors('subject');

    $this->actingAs($this->admin)->delete("/settings/message-templates/{$t->id}")->assertRedirect();
    expect(MessageTemplate::query()->count())->toBe(0);
});

it('lists templates only for people allowed to see them', function () {
    ($this->template)();
    $this->actingAs($this->admin)->get('/settings/message-templates')->assertInertia(fn (Assert $p) => $p->component('Messages/Templates')->has('templates', 1)->where('can.manage', true));

    $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $counselor->assignRole('counselor');
    $this->actingAs($counselor)->get('/settings/message-templates')->assertForbidden();
    $this->actingAs($counselor)->post('/settings/message-templates', ['channel' => 'sms', 'name' => 'A', 'body' => 'Hi'])->assertForbidden();
});

it('fills variables', function () {
    $renderer = app(TemplateRenderer::class);
    $applicant = ($this->person)();
    $values = $renderer->valuesFor($applicant->load('organization'), $this->admin);

    expect($renderer->render('Chào {{ applicant.first_name }} - {{organization.name}} - {{sender.name}}', $values))->toBe('Chào An - Đại học Demo - Cô Lan');
    expect($renderer->unknown('{{applicant.full_name}} {{foo.bar}}'))->toBe(['foo.bar']);
});

it('previews a bulk send with consent and contact counts without writing anything', function () {
    $t = ($this->template)();
    ($this->person)();
    ($this->person)(['consent_marketing' => false, 'phone' => '0912345679']);
    ($this->person)(['phone' => null]);

    $this->actingAs($this->admin)->postJson('/messages/preview', ['template_id' => $t->id])
        ->assertOk()->assertJson(['total' => 3, 'recipients' => 1, 'no_consent' => 1, 'no_contact' => 1, 'sample' => 'Chào An, Đại học Demo nhắc bạn nộp hồ sơ.']);

    expect(MessageLog::query()->count())->toBe(0);
});

it('sends to the filtered applicants, logs each message and writes the timeline', function () {
    $t = ($this->template)();
    $an = ($this->person)(['graduation_year' => 2027]);
    ($this->person)(['graduation_year' => 2028, 'phone' => '0912345679']);

    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $t->id, 'filter' => ['graduation_year' => '2027']])
        ->assertOk()->assertJson(['queued' => 1]);

    $log = MessageLog::query()->firstOrFail();
    expect($log->status)->toBe(MessageStatus::Sent)
        ->and($log->applicant_id)->toBe($an->id)
        ->and($log->recipient)->toBe('84912345678')
        ->and($log->body)->toBe('Chào An, Đại học Demo nhắc bạn nộp hồ sơ.')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->sent_at)->not->toBeNull();

    $activity = Activity::query()->where('subject_id', $an->id)->where('type', 'sms')->first();
    expect($activity)->not->toBeNull()->and($activity->direction)->toBe('out');
});

it('sends email to the applicant address', function () {
    $t = ($this->template)(['channel' => 'email', 'subject' => 'Gửi {{applicant.first_name}}']);
    ($this->person)();

    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $t->id])->assertOk()->assertJson(['queued' => 1]);

    $log = MessageLog::query()->firstOrFail();
    expect($log->recipient)->toBe('an@example.com')->and($log->subject)->toBe('Gửi An');
});

it('records a failure when the sms gateway is not configured or refuses', function () {
    config(['communication.drivers.sms' => 'http', 'communication.sms.url' => null]);
    $t = ($this->template)();
    ($this->person)();

    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $t->id])->assertOk();
    expect(MessageLog::query()->first())->status->toBe(MessageStatus::Failed)->error->toContain('SMS_API_URL');

    config(['communication.sms.url' => 'https://sms.test/send']);
    Http::fake(['sms.test/*' => Http::response(['error' => 'x'], 500)]);
    ($this->person)(['phone' => '0912345679']);
    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $t->id, 'filter' => ['q' => '0912345679']])->assertOk();
    expect(MessageLog::query()->latest('id')->first()->error)->toContain('500');
});

it('sends zalo zns with the approved template id and template data', function () {
    config(['communication.drivers.zns' => 'zalo', 'communication.zns.access_token' => 'tok']);
    Http::fake(['business.openapi.zalo.me/*' => Http::response(['error' => 0, 'message' => 'Success', 'data' => ['msg_id' => 'm-1']])]);
    $t = ($this->template)(['channel' => 'zns', 'provider_template_id' => '123456']);
    ($this->person)();

    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $t->id])->assertOk();

    expect(MessageLog::query()->first())->status->toBe(MessageStatus::Sent)->provider_message_id->toBe('m-1');
    Http::assertSent(fn ($r) => $r->hasHeader('access_token', 'tok') && $r['template_id'] === '123456' && $r['phone'] === '84912345678' && $r['template_data']['applicant_first_name'] === 'An');
});

it('records the refusal of zalo zns', function () {
    config(['communication.drivers.zns' => 'zalo', 'communication.zns.access_token' => 'tok']);
    Http::fake(['business.openapi.zalo.me/*' => Http::response(['error' => -124, 'message' => 'Token het han'])]);
    $t = ($this->template)(['channel' => 'zns', 'provider_template_id' => '1']);
    ($this->person)();

    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $t->id])->assertOk();

    expect(MessageLog::query()->first())->status->toBe(MessageStatus::Failed)->error->toContain('Token het han');
});

it('never delivers a message twice', function () {
    $t = ($this->template)();
    $a = ($this->person)();
    $log = MessageLog::query()->create(['organization_id' => $this->org->id, 'template_id' => $t->id, 'channel' => 'sms', 'recipient' => '84912345678', 'applicant_id' => $a->id, 'body' => 'x', 'status' => 'sent']);

    DeliverMessage::dispatchSync($log->id);

    expect(Activity::query()->where('type', 'sms')->count())->toBe(0);
});

it('refuses inactive templates, too many recipients, and users who may not send', function () {
    $off = ($this->template)(['is_active' => false]);
    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $off->id])->assertUnprocessable();

    config(['communication.max_bulk' => 1]);
    $t = ($this->template)(['name' => 'On']);
    ($this->person)();
    ($this->person)(['phone' => '0912345679']);
    $this->actingAs($this->admin)->postJson('/messages/send', ['template_id' => $t->id])->assertUnprocessable()->assertJsonValidationErrors('filter');

    $faculty = User::factory()->for($this->org)->create();
    $faculty->assignRole('faculty_viewer');
    $this->actingAs($faculty)->postJson('/messages/send', ['template_id' => $t->id])->assertForbidden();
});

it('does not message applicants or use templates of another organization', function () {
    $other = Organization::factory()->create();
    Applicant::factory()->for($other)->create(['phone' => '0987654321', 'consent_marketing' => true]);
    $foreign = MessageTemplate::query()->create(['organization_id' => $other->id, 'channel' => 'sms', 'name' => 'F', 'body' => 'x']);
    $t = ($this->template)();

    $this->actingAs($this->admin)->postJson('/messages/preview', ['template_id' => $t->id])->assertJson(['total' => 0]);
    $this->actingAs($this->admin)->postJson('/messages/preview', ['template_id' => $foreign->id])->assertNotFound();
});

it('lists the log with filters', function () {
    $t = ($this->template)();
    $a = ($this->person)();
    MessageLog::query()->create(['organization_id' => $this->org->id, 'template_id' => $t->id, 'channel' => 'sms', 'recipient' => '84912345678', 'applicant_id' => $a->id, 'body' => 'x', 'status' => 'failed', 'error' => 'boom']);
    MessageLog::query()->create(['organization_id' => $this->org->id, 'template_id' => $t->id, 'channel' => 'sms', 'recipient' => '84912345678', 'applicant_id' => $a->id, 'body' => 'y', 'status' => 'sent']);

    $this->actingAs($this->admin)->get('/messages?filter[status]=failed')->assertInertia(
        fn (Assert $p) => $p->component('Messages/Index')->has('logs.data', 1)->where('logs.data.0.error', 'boom'),
    );
});
