<?php

use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\HighSchool\Actions\ImportHighSchools;
use App\Domain\Intake\Actions\ApplicantImport;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Exports\ImportTemplate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
});

it('downloads an xlsx template for each import', function (string $type, string $file) {
    $this->actingAs($this->admin)->get("/import-templates/{$type}")->assertOk()->assertDownload($file);
})->with([['applicants', 'mau-nhap-thi-sinh.xlsx'], ['high-schools', 'mau-nhap-truong-thpt.xlsx']]);

it('has a data sheet whose headings the importers recognise, and a guide sheet', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';

    foreach (['applicants', 'high-schools'] as $type) {
        file_put_contents($tmp, Excel::raw(new ImportTemplate($type), ExcelType::XLSX));
        $sheets = Excel::toArray(new class implements ToArray
        {
            public function array(array $array): void {}
        }, new UploadedFile($tmp, 'mau.xlsx', null, null, true));

        expect($sheets)->toHaveCount(2)->and($sheets[0])->toHaveCount(1);
        $headings = $sheets[0][0];

        if ($type === 'applicants') {
            expect(array_keys(app(ApplicantImport::class)->suggest($headings)))->toEqualCanonicalizing(array_keys(ApplicantImport::FIELDS));
        } else {
            $normalised = array_map(fn ($h) => Str::slug($h, '_'), $headings);
            $aliases = (new ReflectionClassConstant(ImportHighSchools::class, 'ALIASES'))->getValue();

            foreach ($aliases as $field => $names) {
                expect(array_intersect($names, $normalised))->not->toBeEmpty("no heading for {$field}");
            }
        }

        expect($sheets[1][0])->toBe(['Cột', 'Yêu cầu', 'Giá trị hợp lệ', 'Ví dụ'])->and($sheets[1][1][0])->toBe('Quy tắc chung');
    }

    @unlink($tmp);
});

it('rejects unknown types and users without the import permission', function () {
    $this->actingAs($this->admin)->get('/import-templates/unknown')->assertNotFound();

    $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $counselor->assignRole('counselor');

    $this->actingAs($counselor)->get('/import-templates/high-schools')->assertForbidden();
});

it('requires login', function () {
    $this->get('/import-templates/applicants')->assertRedirect('/login');
});
