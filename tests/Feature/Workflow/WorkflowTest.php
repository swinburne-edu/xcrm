<?php

use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Communication\Models\MessageLog;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.2: workflows (trigger, conditions, actions), the engine, the scheduler and the settings screen.
 * Module under test: app/Domain/Workflow and WorkflowController. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['workflows.resolve_dns' => false, 'communication.drivers.sms' => 'log']);

    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->agent = User::factory()->for($this->org)->create(['name' => 'Tư vấn viên', 'data_scope' => DataScopeLevel::All]);

    $this->stage = fn (string $code) => PipelineStage::query()->whereHas('pipeline', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $this->org->id))->where('code', $code)->firstOrFail();
    $this->flow = fn (array $attrs = []) => Workflow::query()->create([
        'organization_id' => $this->org->id, 'name' => 'WF', 'object' => 'admission_case', 'trigger' => ['type' => 'created'], 'conditions' => [],
        'actions' => [['type' => 'update_field', 'field' => 'temperature', 'value' => 'hot']], 'is_active' => true, ...$attrs,
    ]);
    $this->newCase = fn (array $applicant = [], array $data = []) => app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create($applicant), $this->cycle, ['owner_id' => null, ...$data]);
});

it('runs the actions when a case is created and records the run', function () {
    ($this->flow)(['actions' => [
        ['type' => 'assign', 'user_id' => $this->agent->id],
        ['type' => 'create_task', 'title' => 'Gọi lần đầu', 'due_in_hours' => 2, 'assignee' => 'owner'],
        ['type' => 'update_field', 'field' => 'temperature', 'value' => 'hot'],
    ]]);

    $case = ($this->newCase)()->refresh();

    expect($case->owner_id)->toBe($this->agent->id)->and($case->temperature->value)->toBe('hot');
    expect(Task::query()->where('assignee_id', $this->agent->id)->where('title', 'Gọi lần đầu')->count())->toBe(1);
    $run = WorkflowRun::query()->firstOrFail();
    expect($run->status->value)->toBe('success')->and($run->log)->toHaveCount(3)->and($run->subject_id)->toBe($case->id);
});

it('only runs when the conditions hold', function () {
    ($this->flow)(['conditions' => [['field' => 'owner_id', 'operator' => 'empty'], ['field' => 'score', 'operator' => 'gt', 'value' => 50]]]);

    ($this->newCase)();
    expect(WorkflowRun::query()->count())->toBe(0);

    ($this->flow)(['name' => 'Pool', 'conditions' => [['field' => 'owner_id', 'operator' => 'empty']]]);
    ($this->newCase)();
    expect(WorkflowRun::query()->count())->toBe(1);
});

it('reacts to a stage change and to a watched field, not to others', function () {
    ($this->flow)(['name' => 'Stage', 'trigger' => ['type' => 'stage_changed'], 'conditions' => [['field' => 'stage_id', 'operator' => 'eq', 'value' => ($this->stage)('contacted')->id]],
        'actions' => [['type' => 'create_task', 'title' => 'Theo dõi', 'assignee' => $this->agent->id]]]);
    ($this->flow)(['name' => 'Field', 'trigger' => ['type' => 'updated', 'field' => 'primary_major_id'], 'actions' => [['type' => 'update_field', 'field' => 'temperature', 'value' => 'warm']]]);
    $case = ($this->newCase)();

    app(MoveCaseStage::class)->handle($case, ($this->stage)('contacted'), $this->admin);
    expect(Task::query()->where('title', 'Theo dõi')->count())->toBe(1);

    $case->refresh()->update(['score' => 5]);
    expect(WorkflowRun::query()->whereHas('workflow', fn ($q) => $q->where('name', 'Field'))->count())->toBe(0);

    $case->refresh()->update(['primary_major_id' => null, 'source_id' => null]);
    expect(WorkflowRun::query()->count())->toBe(1);
});

it('does not start itself again through its own changes', function () {
    ($this->flow)(['trigger' => ['type' => 'updated', 'field' => 'temperature'], 'actions' => [['type' => 'update_field', 'field' => 'temperature', 'value' => 'cold']]]);
    $case = ($this->newCase)();

    $case->refresh()->update(['temperature' => 'hot']);

    expect(WorkflowRun::query()->count())->toBe(1)->and($case->refresh()->temperature->value)->toBe('cold');
});

it('logs a failing action and still runs the next one', function () {
    ($this->flow)(['actions' => [
        ['type' => 'create_task', 'title' => 'X', 'assignee' => 'owner'],
        ['type' => 'update_field', 'field' => 'temperature', 'value' => 'warm'],
    ]]);

    $case = ($this->newCase)()->refresh();

    $run = WorkflowRun::query()->firstOrFail();
    expect($run->status->value)->toBe('partial')->and($run->log[0]['ok'])->toBeFalse()->and($run->log[0]['message'])->toContain('kho chung')->and($case->temperature->value)->toBe('warm');
});

it('sends a message only to applicants who agreed', function () {
    $template = MessageTemplate::query()->create(['organization_id' => $this->org->id, 'channel' => 'sms', 'name' => 'Chào', 'body' => 'Chào {{applicant.first_name}}']);
    ($this->flow)(['actions' => [['type' => 'send_message', 'template_id' => $template->id]]]);

    ($this->newCase)(['phone' => '0912345678', 'consent_marketing' => false]);
    expect(WorkflowRun::query()->latest('id')->first()->status->value)->toBe('failed')->and(MessageLog::query()->count())->toBe(0);

    ($this->newCase)(['phone' => '0912345679', 'consent_marketing' => true]);
    expect(WorkflowRun::query()->latest('id')->first()->status->value)->toBe('success')->and(MessageLog::query()->count())->toBe(1);
});

it('calls a webhook but never an internal address', function () {
    Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);
    ($this->flow)(['name' => 'Ok', 'actions' => [['type' => 'webhook', 'url' => 'https://hooks.example.com/in']]]);
    ($this->flow)(['name' => 'Bad', 'actions' => [['type' => 'webhook', 'url' => 'https://127.0.0.1/in'], ['type' => 'webhook', 'url' => 'http://hooks.example.com/in']]]);

    ($this->newCase)();

    Http::assertSentCount(1);
    $bad = WorkflowRun::query()->whereHas('workflow', fn ($q) => $q->where('name', 'Bad'))->firstOrFail();
    expect($bad->status->value)->toBe('failed')->and($bad->log[0]['message'])->toContain('nội bộ');
});

it('runs on new applicants', function () {
    Http::fake(['hooks.example.com/*' => Http::response('ok')]);
    ($this->flow)(['object' => 'applicant', 'conditions' => [['field' => 'graduation_year', 'operator' => 'eq', 'value' => 2027]],
        'actions' => [['type' => 'webhook', 'url' => 'https://hooks.example.com/a']]]);

    Applicant::factory()->for($this->org)->create(['graduation_year' => 2027]);
    Applicant::factory()->for($this->org)->create(['graduation_year' => 2030]);

    expect(WorkflowRun::query()->count())->toBe(1);
});

it('ignores inactive workflows and those of another organization', function () {
    ($this->flow)(['is_active' => false]);
    Workflow::query()->create(['organization_id' => Organization::factory()->create()->id, 'name' => 'Other', 'object' => 'admission_case', 'trigger' => ['type' => 'created'],
        'actions' => [['type' => 'update_field', 'field' => 'temperature', 'value' => 'hot']], 'is_active' => true]);

    ($this->newCase)();

    expect(WorkflowRun::query()->count())->toBe(0);
});

it('runs scheduled workflows once per record', function () {
    $case = ($this->newCase)();
    ($this->flow)(['trigger' => ['type' => 'schedule'], 'conditions' => [['field' => 'temperature', 'operator' => 'empty']]]);

    $this->artisan('crm:run-workflows')->assertSuccessful();
    expect(WorkflowRun::query()->count())->toBe(1)->and($case->refresh()->temperature->value)->toBe('hot');

    $case->update(['temperature' => null]);
    $this->artisan('crm:run-workflows')->assertSuccessful();
    expect(WorkflowRun::query()->count())->toBe(1);
});

it('creates, edits, switches and deletes workflows and validates them', function () {
    $payload = ['name' => 'Giao hồ sơ nóng', 'object' => 'admission_case', 'trigger' => ['type' => 'created'], 'conditions' => [['field' => 'score', 'operator' => 'gt', 'value' => 10]],
        'actions' => [['type' => 'assign', 'user_id' => $this->agent->id]], 'is_active' => true];

    $this->actingAs($this->admin)->post('/settings/workflows', $payload)->assertRedirect();
    $w = Workflow::query()->firstOrFail();
    expect($w->is_active)->toBeTrue();

    $this->actingAs($this->admin)->put("/settings/workflows/{$w->id}", [...$payload, 'name' => 'Đổi tên'])->assertRedirect();
    expect($w->fresh()->name)->toBe('Đổi tên');

    $this->actingAs($this->admin)->patch("/settings/workflows/{$w->id}/active", ['is_active' => false])->assertRedirect();
    expect($w->fresh()->is_active)->toBeFalse();

    $bad = fn (array $over) => $this->actingAs($this->admin)->post('/settings/workflows', [...$payload, ...$over]);
    $bad(['trigger' => ['type' => 'updated']])->assertSessionHasErrors('trigger.field');
    $bad(['object' => 'applicant', 'actions' => [['type' => 'assign', 'user_id' => $this->agent->id]]])->assertSessionHasErrors('actions.0.type');
    $bad(['actions' => [['type' => 'webhook', 'url' => 'http://x.example.com']]])->assertSessionHasErrors('actions.0.type');
    $bad(['actions' => [['type' => 'send_message', 'template_id' => 999]]])->assertSessionHasErrors('actions.0.type');
    $bad(['conditions' => [['field' => 'nope', 'operator' => 'eq', 'value' => 1]]])->assertSessionHasErrors('conditions.0.field');
    $bad(['conditions' => [['field' => 'score', 'operator' => 'eq']]])->assertSessionHasErrors('conditions.0.value');
    $bad(['actions' => []])->assertSessionHasErrors('actions');

    $this->actingAs($this->admin)->delete("/settings/workflows/{$w->id}")->assertRedirect();
    expect(Workflow::query()->count())->toBe(0);
});

it('shows workflows with their latest runs only to people who may see them', function () {
    $w = ($this->flow)();
    ($this->newCase)();

    $this->actingAs($this->admin)->get('/settings/workflows')->assertInertia(
        fn (Assert $p) => $p->component('Workflows/Index')->has('workflows', 1)->where('workflows.0.id', $w->id)->has('workflows.0.runs', 1)->where('can.manage', true),
    );

    $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $counselor->assignRole('counselor');
    $this->actingAs($counselor)->get('/settings/workflows')->assertForbidden();
    $this->actingAs($counselor)->post('/settings/workflows', ['name' => 'x'])->assertForbidden();
});
