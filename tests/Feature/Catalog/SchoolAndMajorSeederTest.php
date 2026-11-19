<?php

use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\HighSchool\Enums\SchoolType;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\HighSchoolSeeder;
use Database\Seeders\MajorSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->dir = sys_get_temp_dir().'/xcrm-schools-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    File::put("{$this->dir}/tinh_thanh_pho.json", json_encode([['province_code' => '01', 'province_name' => 'Thành phố Hà Nội', 'province_type' => 'Thành phố']]));
    File::put("{$this->dir}/xa_phuong.json", json_encode([['ward_code' => '00004', 'ward_name' => 'Phường Ba Đình', 'ward_type' => 'Phường', 'province_code' => '01']]));

    $row = fn (string $code, string $name, string $ward, string $region, string $address = 'Hà Nội') => ['province_code' => '01', 'province_name' => 'Hà Nội', 'ward_code' => $ward, 'ward_name' => 'x', 'school_code' => $code, 'school_name' => $name, 'address' => $address, 'region' => $region];
    File::put("{$this->dir}/danh_sach_truong_thpt_2026.json", json_encode([
        $row('066', 'THPT Phan Đình Phùng', '00004', 'KV3'),
        $row('401', 'GDNN-GDTX Ba Đình', '00004', 'KV2_NT'),
        $row('011', 'THPT Chuyên Hà Nội - Amsterdam', '00004', 'KV1'),
        $row('800', 'Học ở nước ngoài', '0008201', 'KV3', 'Sở GDĐT Hà Nội'),
    ]));

    $catalog = app(CatalogSeeder::class);
    $catalog->dataDirectory = $this->dir;
    $catalog->run();

    $this->seeder = app(HighSchoolSeeder::class);
    $this->seeder->dataDirectory = $this->dir;
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('seeds the schools with province, ward, priority area and a type guessed from the name', function () {
    expect($this->seeder->seedFor($this->org))->toBe(4);

    $school = HighSchool::query()->withoutGlobalScopes()->where('code', '01066')->firstOrFail();

    expect($school->organization_id)->toBe($this->org->id)->and($school->name)->toBe('THPT Phan Đình Phùng')
        ->and($school->province->name)->toBe('Hà Nội')->and($school->ward?->code)->toBe('00004')
        ->and($school->priorityArea?->code)->toBe('KV3')->and($school->type)->toBe(SchoolType::Public)
        ->and($school->partnership_status->value)->toBe('not_contacted');

    $byCode = HighSchool::query()->withoutGlobalScopes()->pluck('type', 'code');
    expect($byCode['01401'])->toBe(SchoolType::ContinuingEdu)->and($byCode['01011'])->toBe(SchoolType::Gifted)
        ->and(HighSchool::query()->withoutGlobalScopes()->where('code', '01401')->first()->priorityArea->code)->toBe('KV2-NT')
        ->and(HighSchool::query()->withoutGlobalScopes()->where('code', '01800')->first()->ward_id)->toBeNull();
});

it('runs again without duplicating and keeps what users edited', function () {
    $this->seeder->seedFor($this->org);
    HighSchool::query()->withoutGlobalScopes()->where('code', '01066')->update(['name' => 'Tên cũ', 'tier' => 'A', 'partnership_status' => 'partner']);

    $this->seeder->seedFor($this->org);

    $school = HighSchool::query()->withoutGlobalScopes()->where('code', '01066')->firstOrFail();
    expect(HighSchool::query()->withoutGlobalScopes()->count())->toBe(4)
        ->and($school->name)->toBe('THPT Phan Đình Phùng')->and($school->tier?->value)->toBe('A')->and($school->partnership_status->value)->toBe('partner');
});

it('keeps each organization apart', function () {
    $other = Organization::factory()->create();

    $this->seeder->seedFor($this->org);
    $this->seeder->seedFor($other);

    expect(HighSchool::query()->withoutGlobalScopes()->count())->toBe(8)
        ->and(HighSchool::query()->withoutGlobalScopes()->where('organization_id', $other->id)->count())->toBe(4);
});

it('skips quietly when the school list is missing', function () {
    $this->seeder->dataDirectory = sys_get_temp_dir().'/xcrm-nothing-'.uniqid();

    expect($this->seeder->seedFor($this->org))->toBe(0)->and(HighSchool::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('seeds the two majors once per organization', function () {
    $seeder = app(MajorSeeder::class);
    $seeder->seedFor($this->org);
    $seeder->seedFor($this->org);

    $majors = Major::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->orderBy('id')->get();

    expect($majors->pluck('name')->all())->toBe(['Leadership and Management', 'Marketing and Communication'])
        ->and($majors->every(fn (Major $m) => $m->is_active && $m->level->value === 'university'))->toBeTrue();
});

it('parses the real MOET school list: every school gets a province and a priority area', function () {
    $catalog = app(CatalogSeeder::class);
    $catalog->dataDirectory = database_path('data');
    $catalog->run();
    $this->seeder->dataDirectory = database_path('data');

    $count = $this->seeder->seedFor($this->org);
    $schools = HighSchool::query()->withoutGlobalScopes()->where('organization_id', $this->org->id);

    expect($count)->toBe(4922)->and((clone $schools)->count())->toBe(4922)
        ->and((clone $schools)->whereNull('province_id')->count())->toBe(0)
        ->and((clone $schools)->whereNull('priority_area_id')->count())->toBe(0)
        ->and(PriorityArea::query()->count())->toBe(4);
});
