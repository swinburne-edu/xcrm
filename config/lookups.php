<?php

use App\Domain\Admission\Lookups\CaseLookup;
use App\Domain\Applicant\Lookups\ApplicantLookup;
use App\Domain\Campaign\Lookups\CampaignLookup;
use App\Domain\Catalog\Lookups\ProvinceLookup;
use App\Domain\Catalog\Lookups\SubjectLookup;
use App\Domain\Catalog\Lookups\WardLookup;
use App\Domain\HighSchool\Lookups\HighSchoolLookup;
use App\Domain\Major\Lookups\CampusLookup;
use App\Domain\Major\Lookups\FacultyLookup;
use App\Domain\Major\Lookups\MajorLookup;
use App\Domain\Platform\Lookups\TeamLookup;
use App\Domain\Platform\Lookups\UserLookup;

/*
|--------------------------------------------------------------------------
| EntityPicker sources: GET /lookups/{entity}?q=...&ids=1,2
|--------------------------------------------------------------------------
|
| Each class extends App\Domain\Platform\Lookups\EloquentLookup.
| Add entries as their tables are built, e.g. (task 1.1) 'provinces', 'wards'
| (used by <ProvinceWardSelect>), (1.8) 'high_schools', (1.6) 'majors',
| (1.10) 'applicants'.
|
*/

return [
    'users' => UserLookup::class,
    'teams' => TeamLookup::class,
    'provinces' => ProvinceLookup::class,
    'wards' => WardLookup::class,
    'subjects' => SubjectLookup::class,
    'majors' => MajorLookup::class,
    'cases' => CaseLookup::class,
    'applicants' => ApplicantLookup::class,
    'campaigns' => CampaignLookup::class,
    'high_schools' => HighSchoolLookup::class,
    'faculties' => FacultyLookup::class,
    'campuses' => CampusLookup::class,
];
