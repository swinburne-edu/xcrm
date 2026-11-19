<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Telephony\Models\CallIntegration;
use App\Domain\Telephony\Models\CallLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Task 3.7: the signed call webhook of the telephone system (ringing, answered, ended), matching callers to
 * applicants, the timeline entry and the call-back task for missed calls. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->agent = User::factory()->for($this->org)->create(['email' => 'tuvan@truong.edu.vn', 'data_scope' => DataScopeLevel::All]);
    $this->integration = CallIntegration::query()->create(['organization_id' => $this->org->id, 'name' => 'Tong dai', 'secret' => 'shh-secret']);

    $this->send = function (array $body, ?string $secret = 'shh-secret', ?CallIntegration $to = null) {
        $json = json_encode($body);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'sha256='.hash_hmac('sha256', $json, (string) $secret)];

        return test()->call('POST', '/api/webhooks/calls/'.($to ?? $this->integration)->uuid, [], [], [], $headers, $json);
    };
    $this->event = fn (array $over = []) => ['call_id' => 'c-1', 'event' => 'ringing', 'direction' => 'in', 'from' => '0912345678', 'to' => '02812345678', ...$over];
});

it('rejects a bad signature, an inactive endpoint and a malformed body', function () {
    ($this->send)(($this->event)(), 'wrong')->assertForbidden();
    expect(CallLog::query()->count())->toBe(0);

    ($this->send)(['call_id' => 'x'])->assertUnprocessable();

    $this->integration->update(['is_active' => false]);
    ($this->send)(($this->event)())->assertNotFound();
});

it('creates an applicant and a case for an unknown caller, once', function () {
    ($this->send)(($this->event)())->assertOk()->assertJson(['status' => 'ringing']);
    ($this->send)(($this->event)())->assertOk();

    $log = CallLog::query()->firstOrFail();
    expect(CallLog::query()->count())->toBe(1)->and($log->applicant_id)->not->toBeNull()->and($log->phone_normalized)->toBe('84912345678');
    expect(Applicant::query()->where('phone_normalized', '84912345678')->count())->toBe(1);
});

it('matches a known caller and the agent named in the event', function () {
    $known = Applicant::factory()->for($this->org)->create(['phone' => '0912345678']);

    ($this->send)(($this->event)(['agent_email' => 'tuvan@truong.edu.vn']))->assertOk();

    $log = CallLog::query()->firstOrFail();
    expect($log->applicant_id)->toBe($known->id)->and($log->user_id)->toBe($this->agent->id);
    expect(Applicant::query()->count())->toBe(1);
});

it('writes one timeline entry when an answered call ends, with duration and recording', function () {
    $known = Applicant::factory()->for($this->org)->create(['phone' => '0912345678']);
    ($this->send)(($this->event)(['agent_email' => 'tuvan@truong.edu.vn']))->assertOk();
    ($this->send)(($this->event)(['event' => 'answered']))->assertOk()->assertJson(['status' => 'answered']);
    ($this->send)(($this->event)(['event' => 'ended', 'duration_sec' => 95, 'recording_url' => 'https://rec.example.com/1.mp3']))->assertOk()->assertJson(['status' => 'ended']);
    ($this->send)(($this->event)(['event' => 'ended', 'duration_sec' => 95]))->assertOk();

    $activities = Activity::query()->where('subject_id', $known->id)->where('type', 'call')->get();
    expect($activities)->toHaveCount(1)->and($activities[0]->direction)->toBe('in')->and($activities[0]->duration_sec)->toBe(95)->and($activities[0]->recording_url)->toBe('https://rec.example.com/1.mp3');
    expect(Task::query()->where('title', 'Gọi lại khách nhỡ máy')->count())->toBe(0);
});

it('marks an unanswered inbound call as missed and creates a call-back task', function () {
    $known = Applicant::factory()->for($this->org)->create(['phone' => '0912345678']);
    ($this->send)(($this->event)(['agent_email' => 'tuvan@truong.edu.vn']))->assertOk();
    ($this->send)(($this->event)(['event' => 'ended', 'duration_sec' => 0]))->assertOk()->assertJson(['status' => 'missed']);

    expect(Task::query()->where('assignee_id', $this->agent->id)->where('title', 'Gọi lại khách nhỡ máy')->count())->toBe(1);
    expect(Activity::query()->where('subject_id', $known->id)->where('title', 'Cuộc gọi nhỡ')->count())->toBe(1);
});

it('uses the case owner as agent and logs outbound calls to a known applicant only', function () {
    $applicant = Applicant::factory()->for($this->org)->create(['phone' => '0912345678']);
    app(CreateAdmissionCase::class)->handle($applicant, $this->cycle, ['owner_id' => $this->agent->id]);

    ($this->send)(($this->event)(['direction' => 'out', 'from' => '102', 'to' => '0912345678']))->assertOk();
    expect(CallLog::query()->firstOrFail()->user_id)->toBe($this->agent->id);

    ($this->send)(($this->event)(['call_id' => 'c-2', 'direction' => 'out', 'from' => '102', 'to' => '0987000111']))->assertOk();
    expect(CallLog::query()->where('external_id', 'c-2')->first()->applicant_id)->toBeNull()->and(Applicant::query()->count())->toBe(1);
});

it('does not mix organizations: a call id of another organization stays separate', function () {
    $other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($other);
    AdmissionCycle::factory()->for($other)->current()->create();
    $theirs = CallIntegration::query()->create(['organization_id' => $other->id, 'name' => 'Khac', 'secret' => 'other-secret']);

    ($this->send)(($this->event)())->assertOk();
    ($this->send)(($this->event)(), 'other-secret', $theirs)->assertOk();

    expect(CallLog::query()->withoutGlobalScopes()->count())->toBe(2)->and(CallLog::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->count())->toBe(1);
});
