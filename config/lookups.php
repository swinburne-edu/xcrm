<?php

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
];
