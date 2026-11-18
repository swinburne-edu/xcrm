<?php

namespace Database\Seeders;

use App\Domain\Activity\Actions\LogActivity;
use App\Domain\Activity\Actions\SaveAppointment;
use App\Domain\Activity\Actions\SaveTask;
use App\Domain\Admission\Actions\AssignCase;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Actions\SaveCampaign;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\CallResult;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Intake\Models\Form;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Organization\Actions\SaveTeam;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Models\User;
use App\Domain\Admission\Actions\SaveApplication;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for trying the MVP end to end (local / testing only; safe to run twice, it stops when it finds its users).
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Everything goes through the real actions (IntakeLead, MoveCaseStage, LogActivity…), so the demo is also a smoke run of
 * flows F3 (online leads), F4 (counseling up to the first steps) and F6 (a few duplicates to merge).
 * Accounts: demo.head@ / demo.lead@ / demo.counselor1..3@ / demo.marketing@example.com, password "password".
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    private const SCHOOLS = [
        ['THPT Chuyên Hà Nội - Amsterdam', 'A'], ['THPT Chu Văn An', 'A'], ['THPT Việt Đức', 'A'], ['THPT Nguyễn Huệ', 'B'],
        ['THPT Lê Quý Đôn', 'B'], ['THPT Phan Đình Phùng', 'B'], ['THPT Trần Phú', 'C'], ['THPT Quang Trung', 'C'],
    ];

    private const MAJORS = [
        ['7480201', 'CNTT', 'Công nghệ thông tin', 300], ['7340101', 'QTKD', 'Quản trị kinh doanh', 250], ['7340115', 'MKT', 'Marketing', 150],
        ['7340301', 'KT', 'Kế toán', 120], ['7220201', 'NNA', 'Ngôn ngữ Anh', 100],
    ];

    private const LAST = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Vũ', 'Đặng', 'Bùi', 'Đỗ', 'Ngô'];

    private const MIDDLE = ['Văn', 'Thị', 'Minh', 'Ngọc', 'Quang', 'Thu', 'Hữu', 'Khánh'];

    private const FIRST = ['An', 'Bình', 'Châu', 'Dũng', 'Giang', 'Hà', 'Hạnh', 'Hiếu', 'Khoa', 'Lan', 'Linh', 'Long', 'Mai', 'Nam', 'Phúc', 'Quân', 'Sơn', 'Thảo', 'Trang', 'Vy'];

    /** target stage code => how many of the demo leads end there */
    private const DISTRIBUTION = ['new' => 18, 'contacted' => 12, 'counseling' => 12, 'early_applied' => 5, 'eligible' => 3, 'admitted' => 4, 'confirmed' => 2, 'enrolled' => 2, 'lost' => 4];

    private int $seq = 0;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder only runs in local / testing.');

            return;
        }

        $this->call([RoleSeeder::class, CatalogSeeder::class]);

        $admin = User::query()->where('email', 'test@example.com')->first() ?? User::query()->orderBy('id')->first();
        $org = $admin?->organization ?? Organization::query()->first() ?? Organization::factory()->create();

        if (User::query()->where('email', 'demo.head@example.com')->exists()) {
            // Demo data from an earlier version may lack the admission setup (rounds, methods, nguyện vọng): add it.
            app(CurrentOrganization::class)->run($org->id, function () use ($org, $admin): void {
                Auth::setUser($admin);
                mt_srand(2027);
                $cycle = AdmissionCycle::query()->where('is_current', true)->orderByDesc('year')->first();
                $majors = Major::query()->whereIn('admission_code', array_column(self::MAJORS, 1))->get()->all();

                if ($cycle !== null && $majors !== []) {
                    $this->admission($org, $cycle, $majors);
                }
            });

            $this->command?->info('Demo data already seeded.');

            return;
        }

        app(SeedOrganizationCatalogs::class)->handle($org);
        $admin ??= User::factory()->create(['organization_id' => $org->id, 'email' => 'test@example.com', 'name' => 'Test User', 'data_scope' => DataScopeLevel::All]);
        $admin->syncRoles([RoleDefinitions::ADMIN]);

        app(CurrentOrganization::class)->run($org->id, function () use ($org, $admin): void {
            Auth::setUser($admin);
            mt_srand(2027);

            $users = $this->users($org);
            $cycle = $this->cycle($org);
            $majors = $this->majors($org, $cycle);
            $schools = $this->schools($org);
            $campaign = $this->campaign($org, $cycle, $users['marketing']);
            $form = $this->form($org, $campaign);

            $this->leads($org, $cycle, $campaign, $form, $schools, $majors, $users);
            $this->duplicates($org, $schools);
            $this->admission($org, $cycle, $majors);
        });

        $this->command?->info('Demo data ready. Log in as demo.head@example.com (or demo.counselor1@example.com) / password.');
    }

    /** @return array<string, User|list<User>> */
    private function users(Organization $org): array
    {
        $make = function (string $key, string $name, string $role, DataScopeLevel $scope) use ($org): User {
            $user = User::query()->create([
                'organization_id' => $org->id, 'name' => $name, 'email' => "demo.{$key}@example.com",
                'password' => Hash::make(self::PASSWORD), 'data_scope' => $scope, 'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($role);

            return $user;
        };

        $head = $make('head', 'Trưởng phòng Tuyển sinh', 'admission_head', DataScopeLevel::All);
        $lead = $make('lead', 'Trưởng nhóm Hà Nội', 'team_lead', DataScopeLevel::Team);
        $counselors = array_map(fn (int $i) => $make("counselor{$i}", ['', 'Nguyễn Thị Hoa', 'Trần Văn Tùng', 'Lê Minh Anh'][$i], 'counselor', DataScopeLevel::Own), [1, 2, 3]);
        $marketing = $make('marketing', 'Cán bộ Marketing', 'marketing', DataScopeLevel::All);

        app(SaveTeam::class)->handle(null, ['name' => 'Nhóm tư vấn Hà Nội', 'leader_id' => $lead->id, 'member_ids' => array_map(fn (User $u) => $u->id, $counselors)]);

        return ['head' => $head, 'lead' => $lead, 'counselors' => $counselors, 'marketing' => $marketing];
    }

    private function cycle(Organization $org): AdmissionCycle
    {
        $cycle = AdmissionCycle::query()->where('is_current', true)->where('status', '!=', 'closed')->orderByDesc('year')->first();

        if ($cycle === null) {
            $cycle = AdmissionCycle::query()->create(['organization_id' => $org->id, 'name' => 'Tuyển sinh năm 2027', 'year' => 2027, 'status' => 'active', 'is_current' => true]);
        }

        $cycle->update(['target_enrolled' => $cycle->target_enrolled ?? 920]);

        return $cycle;
    }

    /** @return list<Major> */
    private function majors(Organization $org, AdmissionCycle $cycle): array
    {
        $majors = [];

        foreach (self::MAJORS as [$moet, $code, $name, $quota]) {
            $major = Major::query()->firstOrCreate(
                ['organization_id' => $org->id, 'admission_code' => $code],
                ['moet_code' => $moet, 'name' => $name, 'program_type' => 'standard', 'level' => 'university', 'duration_years' => 4, 'is_active' => true],
            );
            MajorQuota::query()->firstOrCreate(['organization_id' => $org->id, 'major_id' => $major->id, 'cycle_id' => $cycle->id], ['quota_total' => $quota, 'quota_direct' => (int) ($quota * 0.1), 'tuition_fee' => 25000000]);
            $majors[] = $major;
        }

        return $majors;
    }

    /** @return list<HighSchool> */
    private function schools(Organization $org): array
    {
        $province = Province::query()->where('name', 'like', '%Hà Nội%')->first();

        return array_map(fn (array $s) => HighSchool::query()->firstOrCreate(
            ['organization_id' => $org->id, 'name' => $s[0]],
            ['code' => 'DEMO'.str_pad((string) (++$this->seq), 3, '0', STR_PAD_LEFT), 'type' => 'public', 'tier' => $s[1], 'partnership_status' => $s[1] === 'A' ? 'partner' : 'not_contacted', 'province_id' => $province?->id],
        ), self::SCHOOLS);
    }

    private function campaign(Organization $org, AdmissionCycle $cycle, User $owner): Campaign
    {
        $campaign = app(SaveCampaign::class)->handle(null, [
            'cycle_id' => $cycle->id, 'code' => 'ts2027-facebook', 'name' => 'Tuyển sinh 2027 - Facebook Ads', 'type' => 'digital_ads', 'channel' => 'facebook',
            'budget' => 150000000, 'target_leads' => 2000, 'target_enrolled' => 150, 'owner_id' => $owner->id,
        ]);
        $campaign->forceFill(['status' => 'running'])->save();

        return $campaign;
    }

    private function form(Organization $org, Campaign $campaign): Form
    {
        return Form::query()->create([
            'organization_id' => $org->id, 'name' => 'Đăng ký tư vấn tuyển sinh 2027', 'campaign_id' => $campaign->id,
            'source_id' => LeadSource::query()->where('code', 'facebook_ads')->value('id'),
            'fields' => [['key' => 'full_name', 'required' => true], ['key' => 'phone', 'required' => true], ['key' => 'email', 'required' => false], ['key' => 'high_school', 'required' => false], ['key' => 'major', 'required' => false]],
            'success_message' => 'Cảm ơn bạn! Cán bộ tuyển sinh sẽ gọi lại trong 24 giờ.',
        ]);
    }

    /**
     * @param  list<HighSchool>  $schools
     * @param  list<Major>  $majors
     * @param  array<string, mixed>  $users
     */
    private function leads(Organization $org, AdmissionCycle $cycle, Campaign $campaign, Form $form, array $schools, array $majors, array $users): void
    {
        $intake = app(IntakeLead::class);
        $sources = LeadSource::query()->whereIn('code', ['website', 'facebook_ads', 'school_counseling', 'hotline', 'open_day'])->pluck('id', 'code')->all();
        $stages = PipelineStage::query()->whereHas('pipeline', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $org->id)->where('is_default', true))->get()->keyBy('code');
        $lost = LostReason::query()->orderBy('id')->pluck('id')->all();
        $results = CallResult::query()->orderBy('id')->get();
        $head = $users['head'];
        $counselors = $users['counselors'];
        $plan = collect(self::DISTRIBUTION)->flatMap(fn (int $n, string $code) => array_fill(0, $n, $code))->shuffle(mt_rand())->values()->all();

        foreach ($plan as $i => $targetStage) {
            $sourceCode = array_keys($sources)[$i % count($sources)];
            $school = $schools[$i % count($schools)];
            $result = $intake->handle($org->id, [
                'full_name' => $this->name($i), 'phone' => '09'.str_pad((string) (10000000 + $i * 137), 8, '0', STR_PAD_LEFT),
                'email' => $i % 3 === 0 ? 'thisinh'.$i.'@example.com' : null,
                'high_school' => $school->id, 'graduation_year' => 2027, 'major' => $majors[$i % count($majors)]->id, 'consent' => true,
            ], [
                'source_id' => $sources[$sourceCode], 'campaign_id' => $sourceCode === 'facebook_ads' ? $campaign->id : null,
                'form_id' => $sourceCode === 'website' || $sourceCode === 'facebook_ads' ? $form->id : null,
                'utm' => $sourceCode === 'facebook_ads' ? ['source' => 'facebook', 'medium' => 'cpc', 'campaign' => $campaign->code] : [],
                'label' => 'Đăng ký tư vấn tuyển sinh 2027',
            ]);

            /** @var AdmissionCase $case */
            $case = $result['case'];

            if ($i % 5 !== 4) {
                $case = app(AssignCase::class)->handle($case, $counselors[$i % 3], null, $head);
            }

            $owner = $case->owner_id === null ? $head : User::query()->find($case->owner_id);
            $this->progress($case, $targetStage, $stages, $owner, $head, $results, $lost, $majors, $i);
        }
    }

    /**
     * @param  Collection<string, PipelineStage>  $stages
     * @param  Collection<int, CallResult>  $results
     * @param  list<int>  $lost
     * @param  list<Major>  $majors
     */
    private function progress(AdmissionCase $case, string $target, $stages, User $owner, User $head, $results, array $lost, array $majors, int $i): void
    {
        if ($target === 'new') {
            return;
        }

        $reached = $results->firstWhere('is_contacted', true);
        app(LogActivity::class)->handle($case, ['type' => 'call', 'call_result_id' => $reached?->id, 'body' => 'Đã tư vấn qua điện thoại về ngành học và học phí.', 'duration_sec' => 180 + ($i % 7) * 60], $owner);

        $path = ['contacted', 'counseling', 'early_applied', 'eligible', 'moet_registered', 'admitted', 'confirmed', 'enrolled'];
        $to = $target === 'lost' ? 'counseling' : $target;

        foreach ($path as $code) {
            if ($code === 'contacted') {
                continue; // reached by the call above
            }

            if (array_search($code, $path, true) > array_search($to, $path, true)) {
                break;
            }

            $case = app(MoveCaseStage::class)->handle($case, $stages[$code], $head, $code === 'enrolled' ? ['enrolled_major_id' => $case->primary_major_id ?? $majors[0]->id] : []);
        }

        if ($target === 'lost') {
            app(MoveCaseStage::class)->handle($case, $stages['lost'], $head, ['lost_reason_id' => $lost[$i % count($lost)], 'lost_note' => 'Thí sinh chọn trường khác.']);

            return;
        }

        if (in_array($target, ['counseling', 'contacted'], true) && $i % 2 === 0) {
            app(SaveAppointment::class)->handle($case, null, ['type' => 'campus_tour', 'start_at' => now(config('app.display_timezone'))->addDays(2 + $i % 5)->format('Y-m-d\T09:00'), 'location' => 'Cơ sở chính'], $owner);
            app(SaveTask::class)->handle(null, ['title' => 'Nhắc lịch tham quan', 'type' => 'call_back', 'due_at' => now(config('app.display_timezone'))->addDay()->format('Y-m-d\T08:30')], $owner, $case);
        }
    }

    /**
     * Two pairs of the same student entered twice at the same school, for the "Có thể trùng" screen (flow F6).
     *
     * @param  list<HighSchool>  $schools
     */
    private function duplicates(Organization $org, array $schools): void
    {
        foreach ([['Nguyễn Văn An', 0], ['Trần Thị Bình', 1]] as $n => [$name, $school]) {
            foreach ([0, 1] as $copy) {
                Applicant::query()->create([
                    'organization_id' => $org->id, 'full_name' => $copy === 0 ? $name : mb_strtoupper($name),
                    'phone' => '0988'.str_pad((string) ($n * 10 + $copy + 100), 6, '0', STR_PAD_LEFT), 'high_school_id' => $schools[$school]->id, 'graduation_year' => 2027,
                ]);
            }
        }
    }

    /**
     * Rounds, two scored methods (THPT exam, transcript), the majors' combinations, and nguyện vọng for the leads that
     * are already at or past "Nộp hồ sơ xét sớm". Skipped when the cycle already has methods or the catalogs are missing.
     *
     * @param  list<Major>  $majors
     */
    private function admission(Organization $org, AdmissionCycle $cycle, array $majors): void
    {
        $methods = AdmissionMethod::query()->whereIn('code', ['100', '200'])->get()->keyBy('code');

        if (CycleMethod::query()->where('cycle_id', $cycle->id)->exists() || $methods->count() < 2) {
            return;
        }

        $early = AdmissionRound::query()->create(['cycle_id' => $cycle->id, 'name' => 'Xét tuyển sớm', 'type' => 'early_school', 'sort_order' => 1]);
        AdmissionRound::query()->create(['cycle_id' => $cycle->id, 'name' => 'Đợt 1 - hệ thống của Bộ', 'type' => 'moet_system', 'sort_order' => 2]);

        $priority = ['apply' => true, 'taper_from' => 22.5];
        $english = ['subject' => 'ANH', 'table' => ['IELTS' => ['5.0' => 8.0, '5.5' => 8.5, '6.0' => 9.0, '6.5' => 9.5, '7.0' => 10]]];
        $thpt = CycleMethod::query()->create(['cycle_id' => $cycle->id, 'method_id' => $methods['100']->id, 'display_name' => 'Điểm thi tốt nghiệp THPT', 'required_documents' => ['citizen_id'],
            'score_formula' => ['source' => 'thpt_exam', 'scale_to' => 30, 'priority' => $priority, 'encourage_bonus_max' => 3, 'min_total' => 18, 'certificate_conversion' => $english]]);
        $transcript = CycleMethod::query()->create(['cycle_id' => $cycle->id, 'method_id' => $methods['200']->id, 'display_name' => 'Xét học bạ THPT', 'required_documents' => ['transcript', 'citizen_id'],
            'score_formula' => ['source' => 'transcript', 'terms' => ['11', '12'], 'aggregate' => 'avg', 'scale_to' => 30, 'priority' => $priority, 'encourage_bonus_max' => 3, 'min_total' => 18]]);

        $combinations = SubjectCombination::query()->whereIn('code', ['A00', 'A01', 'D01'])->pluck('id');

        foreach ($majors as $major) {
            foreach ([$thpt, $transcript] as $method) {
                DB::table('major_cycle_methods')->insertOrIgnore(['major_id' => $major->id, 'cycle_method_id' => $method->id]);
            }

            foreach ($combinations as $combinationId) {
                DB::table('major_combinations')->insertOrIgnore(['major_id' => $major->id, 'combination_id' => $combinationId, 'cycle_id' => $cycle->id]);
            }
        }

        $status = ['early_applied' => 'submitted', 'eligible' => 'eligible', 'moet_registered' => 'eligible', 'admitted' => 'admitted', 'confirmed' => 'confirmed', 'enrolled' => 'confirmed'];
        $subjects = Subject::query()->whereIn('code', ['TOAN', 'LY', 'HOA', 'ANH', 'VAN'])->pluck('id', 'code');
        $cases = AdmissionCase::query()->with('stage')->where('cycle_id', $cycle->id)->orderBy('id')->get()->filter(fn (AdmissionCase $c) => isset($status[$c->stage->code]));
        $save = app(SaveApplication::class);

        foreach ($cases->values() as $i => $case) {
            foreach (['TOAN', 'LY', 'HOA'] as $code) {
                $base = 6 + (($i * 7 + strlen($code) * 3) % 33) / 10; // 6.0 – 9.2, repeatable
                ApplicantScore::query()->create(['applicant_id' => $case->applicant_id, 'score_type' => 'thpt_exam', 'subject_id' => $subjects[$code], 'score' => round($base, 1), 'year' => $cycle->year - 1]);
            }

            $major = $majors[$i % count($majors)];
            $application = $save->handle($case, null, ['major_id' => $major->id, 'cycle_method_id' => $thpt->id, 'combination_id' => $combinations->first(), 'round_id' => $early->id], null);
            $application->forceFill(['status' => $status[$case->stage->code], 'submitted_at' => now()->subDays(3)])->save();
        }
    }

    private function name(int $i): string
    {
        // last name = i mod 10 and middle name = i div 10 already make every i below 80 a different pair
        return self::LAST[$i % count(self::LAST)].' '.self::MIDDLE[intdiv($i, 10) % count(self::MIDDLE)].' '.self::FIRST[($i * 3) % count(self::FIRST)];
    }
}
