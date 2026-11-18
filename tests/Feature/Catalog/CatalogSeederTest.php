<?php

use App\Domain\Catalog\Enums\ProvinceType;
use App\Domain\Catalog\Enums\ScoreSource;
use App\Domain\Catalog\Enums\WardType;
use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\PriorityGroup;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Catalog\Models\Ward;
use App\Domain\Catalog\Models\WardMapping;
use App\Domain\Catalog\Services\CombinationValidator;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

function seedCatalogs(?string $dataDirectory = null): void
{
    $seeder = app(CatalogSeeder::class);
    $seeder->dataDirectory = $dataDirectory ?? sys_get_temp_dir().'/xcrm-no-data-'.uniqid();
    $seeder->run();
}

it('seeds the system catalogs from docs/04-database.md', function () {
    seedCatalogs();

    expect(PriorityArea::pluck('bonus_score', 'code')->map(fn ($v) => (float) $v)->all())
        ->toBe(['KV1' => 0.75, 'KV2-NT' => 0.5, 'KV2' => 0.25, 'KV3' => 0.0])
        ->and(PriorityGroup::pluck('bonus_score', 'code')->map(fn ($v) => (float) $v)->all())
        ->toBe(['UT1' => 2.0, 'UT2' => 1.0])
        ->and(Subject::count())->toBe(12)
        ->and(Subject::where('is_core', true)->pluck('code')->sort()->values()->all())->toBe(['TOAN', 'VAN'])
        ->and(SubjectCombination::count())->toBe(12)
        ->and(AdmissionMethod::pluck('code')->all())->toBe(['100', '200', '301', '401', '402', '405', '500'])
        ->and(AdmissionMethod::where('code', '301')->first()->score_source)->toBe(ScoreSource::Direct);
});

it('seeds combinations with their 3 subjects, all valid under R2', function () {
    seedCatalogs();

    $d01 = SubjectCombination::with('subjects')->where('code', 'D01')->first();

    expect($d01->name)->toBe('Ngữ văn, Toán, Tiếng Anh')
        ->and($d01->subjects->pluck('code')->sort()->values()->all())->toBe(['ANH', 'TOAN', 'VAN']);

    $validator = app(CombinationValidator::class);

    SubjectCombination::with('subjects')->get()->each(function (SubjectCombination $c) use ($validator) {
        $subjects = $c->subjects->map(fn ($s) => ['code' => $s->code, 'weight' => $s->pivot->weight])->all();
        expect($validator->passes($subjects))->toBeTrue("{$c->code} violates R2");
    });
});

it('can run again without duplicating rows', function () {
    seedCatalogs();
    Subject::where('code', 'TIN')->update(['name' => 'Tin học (đã sửa)']);

    seedCatalogs();

    expect(Subject::count())->toBe(12)
        ->and(SubjectCombination::count())->toBe(12)
        ->and(DB::table('combination_subjects')->count())->toBe(36)
        ->and(Subject::where('code', 'TIN')->value('name'))->toBe('Tin học');
});

it('skips administrative units when the data files are missing', function () {
    seedCatalogs();

    expect(Province::count())->toBe(0)->and(Ward::count())->toBe(0);
});

it('loads provinces, wards and ward mappings from JSON files', function () {
    $dir = sys_get_temp_dir().'/xcrm-catalog-'.uniqid();
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/provinces.json", json_encode([
        ['code' => '01', 'name' => 'Hà Nội', 'type' => 'thanh_pho'],
        ['code' => '40', 'name' => 'Nghệ An', 'type' => 'tinh'],
    ]));
    File::put("{$dir}/wards.json", json_encode([
        ['code' => '00004', 'name' => 'Ba Đình', 'type' => 'phuong', 'province_code' => '01'],
        ['code' => '16681', 'name' => 'Vinh Phú', 'type' => 'phuong', 'province_code' => '40'],
        ['code' => '99999', 'name' => 'Không có tỉnh', 'type' => 'xa', 'province_code' => '77'],
    ]));
    File::put("{$dir}/ward_mappings.json", json_encode([
        ['old_province_name' => 'Hà Nội', 'old_district_name' => 'Quận Ba Đình', 'old_ward_name' => 'Phường Phúc Xá', 'new_ward_code' => '00004'],
    ]));

    seedCatalogs($dir);
    seedCatalogs($dir); // idempotent

    expect(Province::count())->toBe(2)
        ->and(Province::where('code', '01')->first()->type)->toBe(ProvinceType::City)
        ->and(Ward::count())->toBe(2)
        ->and(Ward::where('code', '16681')->first()->province->name)->toBe('Nghệ An')
        ->and(Ward::where('code', '00004')->first()->type)->toBe(WardType::Ward)
        ->and(WardMapping::count())->toBe(1)
        ->and(WardMapping::first()->newWard->name)->toBe('Ba Đình');

    File::deleteDirectory($dir);
});
