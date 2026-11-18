<?php

use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Report\Services\FunnelReport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    app(SeedOrganizationCatalogs::class)->handle($this->other);

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['target_enrolled' => 10]);
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->source = fn (string $code) => LeadSource::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('code', $code)->value('id');
    $this->school = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Chuyên A']);
    $this->stage = fn (string $code) => PipelineStage::query()->whereHas('pipeline', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $this->org->id))->where('code', $code)->firstOrFail()->id;
    $this->makeCase = function (string $source = 'website', string $stage = 'new', array $applicant = [], array $case = [], ?User $owner = null) {
        $c = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create($applicant), $this->cycle, ['owner_id' => ($owner ?? $this->admin)->id, 'source_id' => ($this->source)($source)], $this->admin);
        AdmissionCase::query()->whereKey($c->id)->update(['stage_id' => ($this->stage)($stage)] + $case);

        return $c->refresh();
    };
});

describe('FunnelReport', function () {
    it('counts the funnel per stage, the KPIs and the enrolled target', function () {
        $this->actingAs($this->admin);
        ($this->makeCase)();
        ($this->makeCase)('website', 'contacted', [], ['first_contacted_at' => now()]);
        ($this->makeCase)('website', 'admitted');
        ($this->makeCase)('website', 'enrolled');
        ($this->makeCase)('website', 'lost');

        $overview = app(FunnelReport::class)->overview($this->cycle, $this->admin);

        expect($overview['kpis'])->toMatchArray(['total' => 5, 'open' => 3, 'admitted' => 2, 'enrolled' => 1, 'lost' => 1, 'target_enrolled' => 10, 'new_this_week' => 5])
            ->and(collect($overview['funnel'])->pluck('count', 'name')->all())->toMatchArray(['Mới' => 1, 'Đã liên hệ' => 1, 'Trúng tuyển' => 1, 'Nhập học' => 1, 'Thất bại' => 1, 'Đang tư vấn' => 0])
            ->and($overview['funnel'])->toHaveCount(10);
    });

    it('fills the last 30 days with zeros and counts today', function () {
        $this->actingAs($this->admin);
        ($this->makeCase)();
        $old = ($this->makeCase)();
        AdmissionCase::query()->whereKey($old->id)->update(['created_at' => now()->subDays(3)]);

        $daily = app(FunnelReport::class)->daily($this->cycle);

        expect($daily)->toHaveCount(30)->and(end($daily)['count'])->toBe(1)
            ->and(collect($daily)->sum('count'))->toBe(2)
            ->and($daily[26]['count'])->toBe(1);
    });

    it('breaks the funnel down by source with conversion rate, biggest first', function () {
        $this->actingAs($this->admin);
        ($this->makeCase)('facebook_ads');
        ($this->makeCase)('facebook_ads', 'enrolled', [], ['first_contacted_at' => now()]);
        ($this->makeCase)('hotline', 'admitted');

        $rows = app(FunnelReport::class)->breakdown($this->cycle, 'source');

        expect($rows)->toHaveCount(2)->and($rows[0])->toMatchArray(['label' => 'Facebook Ads', 'total' => 2, 'contacted' => 1, 'admitted' => 1, 'enrolled' => 1, 'lost' => 0, 'rate' => 50.0])
            ->and($rows[1])->toMatchArray(['label' => 'Hotline', 'total' => 1, 'admitted' => 1, 'rate' => 0.0]);
    });

    it('breaks down by high school, province, major, campaign and counselor, with a row for "unknown"', function () {
        $this->actingAs($this->admin);
        ($this->makeCase)('website', 'new', ['high_school_id' => $this->school->id]);
        ($this->makeCase)('website', 'new', ['high_school_id' => null]);
        $major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);
        ($this->makeCase)('website', 'new', [], ['primary_major_id' => $major->id]);

        $report = app(FunnelReport::class);

        expect(collect($report->breakdown($this->cycle, 'high_school'))->pluck('total', 'label')->all())->toMatchArray(['THPT Chuyên A' => 1, 'Chưa rõ trường' => 2])
            ->and(collect($report->breakdown($this->cycle, 'major'))->pluck('total', 'label')->all())->toMatchArray(['Công nghệ thông tin' => 1, 'Chưa chọn ngành' => 2])
            ->and(collect($report->breakdown($this->cycle, 'owner'))->pluck('total', 'label')->all())->toMatchArray([$this->admin->name => 3])
            ->and($report->breakdown($this->cycle, 'campaign')[0]['label'])->toBe('Không thuộc chiến dịch')
            ->and($report->breakdown($this->cycle, 'province'))->toBeArray()
            ->and($report->breakdown($this->cycle, 'nonsense')[0]['label'])->toBeString();
    });

    it('shows quota progress per major', function () {
        $this->actingAs($this->admin);
        $it = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);
        MajorQuota::query()->create(['organization_id' => $this->org->id, 'major_id' => $it->id, 'cycle_id' => $this->cycle->id, 'quota_total' => 100]);
        ($this->makeCase)('website', 'counseling', [], ['primary_major_id' => $it->id]);
        ($this->makeCase)('website', 'admitted', [], ['primary_major_id' => $it->id]);
        ($this->makeCase)('website', 'enrolled', [], ['primary_major_id' => $it->id, 'enrolled_major_id' => $it->id]);

        expect(app(FunnelReport::class)->quotas($this->cycle))->toBe([
            ['major_id' => $it->id, 'major' => 'Công nghệ thông tin', 'quota' => 100, 'target' => null, 'interested' => 3, 'admitted' => 2, 'enrolled' => 1],
        ]);
    });

    it('only counts the cases the user may see', function () {
        $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $counselor->assignRole('counselor');
        ($this->makeCase)('website', 'new', [], [], $counselor);
        ($this->makeCase)('website', 'new', [], [], $counselor);
        ($this->makeCase)();

        $this->actingAs($this->admin);
        expect(app(FunnelReport::class)->overview($this->cycle, $this->admin)['kpis']['total'])->toBe(3);

        $this->actingAs($counselor);
        $mine = app(FunnelReport::class);

        expect($mine->overview($this->cycle, $counselor)['kpis']['total'])->toBe(2)->and($mine->breakdown($this->cycle, 'owner'))->toHaveCount(1);
    });

    it('never counts another organization or another cycle', function () {
        $this->actingAs($this->admin);
        ($this->makeCase)();
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), AdmissionCycle::factory()->for($this->org)->create(), [], $this->admin);
        $otherCycle = AdmissionCycle::factory()->for($this->other)->current()->create();
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->other)->create(), $otherCycle);

        expect(app(FunnelReport::class)->overview($this->cycle, $this->admin)['kpis']['total'])->toBe(1);
    });

    it('lists my overdue tasks', function () {
        Task::factory()->create(['organization_id' => $this->org->id, 'assignee_id' => $this->admin->id, 'title' => 'Quá hạn', 'due_at' => now()->subDay()]);
        Task::factory()->create(['organization_id' => $this->org->id, 'assignee_id' => $this->admin->id, 'due_at' => now()->addDay()]);

        $this->actingAs($this->admin);
        $tasks = app(FunnelReport::class)->myOverdueTasks($this->admin);

        expect($tasks['count'])->toBe(1)->and($tasks['items'][0]['title'])->toBe('Quá hạn');
    });
});

describe('dashboard and reports screens', function () {
    it('renders the dashboard with the cycle numbers', function () {
        ($this->makeCase)();

        $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('cycle.id', $this->cycle->id)
            ->where('data.kpis.total', 1)->has('data.funnel', 10)->has('data.daily', 30)->has('data.top_sources', 1));
    });

    it('renders the dashboard without data when the organization has no cycle', function () {
        $user = User::factory()->for(Organization::factory()->create())->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('data', null));
    });

    it('serves the report for the chosen dimension and falls back to source', function () {
        ($this->makeCase)('hotline');

        $this->actingAs($this->admin)->get('/reports?dimension=owner')->assertInertia(fn (Assert $page) => $page->component('Reports/Index')->where('dimension', 'owner')->has('rows', 1)->has('dimensions', 6)->where('can.export', true));
        $this->get('/reports?dimension=hack')->assertInertia(fn (Assert $page) => $page->where('dimension', 'source'));
    });

    it('exports the table as an Excel file', function () {
        ($this->makeCase)('hotline');

        $response = $this->actingAs($this->admin)->get('/reports/export?dimension=source');

        $response->assertOk()->assertDownload();
        expect($response->headers->get('content-disposition'))->toContain('bao-cao-pheu-source-');
    });

    it('needs report.view and report.export', function () {
        $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $counselor->assignRole('counselor');

        $this->actingAs($counselor)->get('/reports')->assertForbidden();
        $this->get('/reports/export')->assertForbidden();
        $this->get('/dashboard')->assertOk();
    });
});
