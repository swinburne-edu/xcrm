<?php

use App\Domain\Platform\Services\NavigationMenu;
use App\Http\Controllers\Activity\AppointmentController;
use App\Http\Controllers\Activity\TaskController;
use App\Http\Controllers\Activity\TimelineController;
use App\Http\Controllers\Admission\CaseController;
use App\Http\Controllers\Admission\PipelineController;
use App\Http\Controllers\Applicant\ApplicantCertificateController;
use App\Http\Controllers\Applicant\ApplicantController;
use App\Http\Controllers\Applicant\ApplicantGuardianController;
use App\Http\Controllers\Applicant\ApplicantScoreController;
use App\Http\Controllers\Campaign\CampaignController;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Cycle\CycleController;
use App\Http\Controllers\Cycle\CycleMethodController;
use App\Http\Controllers\Cycle\CycleRoundController;
use App\Http\Controllers\Cycle\CycleSelectionController;
use App\Http\Controllers\HighSchool\HighSchoolController;
use App\Http\Controllers\HighSchool\SchoolContactController;
use App\Http\Controllers\Major\MajorController;
use App\Http\Controllers\Platform\ComponentsDemoController;
use App\Http\Controllers\Platform\LookupController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\Settings\TerritoryController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->prefix('settings')->name('settings.')->group(function () {
    // System catalogs (task 1.1). Authorization: CatalogPolicy.
    Route::get('catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::get('catalogs/{catalog}', [CatalogController::class, 'index'])->name('catalogs.show');
    Route::post('catalogs/{catalog}', [CatalogController::class, 'store'])->name('catalogs.store');
    Route::put('catalogs/{catalog}/{id}', [CatalogController::class, 'update'])->whereNumber('id')->name('catalogs.update');
    Route::delete('catalogs/{catalog}/{id}', [CatalogController::class, 'destroy'])->whereNumber('id')->name('catalogs.destroy');

    // Users, teams, roles (task 1.3). Authorization: UserPolicy / TeamPolicy / role.view.
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/teams', [TeamController::class, 'index'])->name('users.teams');
    Route::get('users/roles', [RoleController::class, 'index'])->name('users.roles');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{id}', [UserController::class, 'update'])->whereNumber('id')->name('users.update');
    Route::delete('users/{id}', [UserController::class, 'destroy'])->whereNumber('id')->name('users.destroy');
    // Responsibility areas (task 1.4). Authorization: TerritoryPolicy.
    Route::get('territories', [TerritoryController::class, 'index'])->name('territories.index');
    Route::post('territories', [TerritoryController::class, 'store'])->name('territories.store');
    Route::put('territories/{id}', [TerritoryController::class, 'update'])->whereNumber('id')->name('territories.update');
    Route::delete('territories/{id}', [TerritoryController::class, 'destroy'])->whereNumber('id')->name('territories.destroy');

    // Admission cycles (task 1.5). Authorization: CyclePolicy (closed cycle = read-only, rule R8).
    Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
    Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
    Route::get('cycles/{id}', [CycleController::class, 'show'])->whereNumber('id')->name('cycles.show');
    Route::put('cycles/{id}', [CycleController::class, 'update'])->whereNumber('id')->name('cycles.update');
    Route::delete('cycles/{id}', [CycleController::class, 'destroy'])->whereNumber('id')->name('cycles.destroy');
    Route::post('cycles/{id}/current', [CycleController::class, 'setCurrent'])->whereNumber('id')->name('cycles.current');
    Route::post('cycles/{id}/clone', [CycleController::class, 'clone'])->whereNumber('id')->name('cycles.clone');
    Route::post('cycles/{id}/reopen', [CycleController::class, 'reopen'])->whereNumber('id')->name('cycles.reopen');
    Route::post('cycles/{cycleModel}/rounds', [CycleRoundController::class, 'store'])->whereNumber('cycleModel')->name('cycles.rounds.store');
    Route::put('cycles/{cycleModel}/rounds/{roundId}', [CycleRoundController::class, 'update'])->whereNumber(['cycleModel', 'roundId'])->name('cycles.rounds.update');
    Route::delete('cycles/{cycleModel}/rounds/{roundId}', [CycleRoundController::class, 'destroy'])->whereNumber(['cycleModel', 'roundId'])->name('cycles.rounds.destroy');
    Route::post('cycles/{cycleModel}/methods', [CycleMethodController::class, 'store'])->whereNumber('cycleModel')->name('cycles.methods.store');
    Route::put('cycles/{cycleModel}/methods/{methodId}', [CycleMethodController::class, 'update'])->whereNumber(['cycleModel', 'methodId'])->name('cycles.methods.update');
    Route::delete('cycles/{cycleModel}/methods/{methodId}', [CycleMethodController::class, 'destroy'])->whereNumber(['cycleModel', 'methodId'])->name('cycles.methods.destroy');

    // Pipelines and stages (task 1.11). Authorization: PipelinePolicy (pipeline.view / pipeline.manage).
    Route::get('pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::post('pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::put('pipelines/{id}', [PipelineController::class, 'update'])->whereNumber('id')->name('pipelines.update');
    Route::delete('pipelines/{id}', [PipelineController::class, 'destroy'])->whereNumber('id')->name('pipelines.destroy');
    Route::post('pipelines/{id}/stages', [PipelineController::class, 'storeStage'])->whereNumber('id')->name('pipelines.stages.store');
    Route::put('pipelines/{id}/stages/{stageId}', [PipelineController::class, 'updateStage'])->whereNumber(['id', 'stageId'])->name('pipelines.stages.update');
    Route::delete('pipelines/{id}/stages/{stageId}', [PipelineController::class, 'destroyStage'])->whereNumber(['id', 'stageId'])->name('pipelines.stages.destroy');

    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::put('teams/{id}', [TeamController::class, 'update'])->whereNumber('id')->name('teams.update');
    Route::delete('teams/{id}', [TeamController::class, 'destroy'])->whereNumber('id')->name('teams.destroy');
});

// Majors (task 1.6). Authorization: MajorPolicy; per-cycle setup also needs an open cycle (R8).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('majors', [MajorController::class, 'index'])->name('majors.index');
    Route::post('majors', [MajorController::class, 'store'])->name('majors.store');
    Route::get('majors/{id}', [MajorController::class, 'show'])->whereNumber('id')->name('majors.show');
    Route::put('majors/{id}', [MajorController::class, 'update'])->whereNumber('id')->name('majors.update');
    Route::delete('majors/{id}', [MajorController::class, 'destroy'])->whereNumber('id')->name('majors.destroy');
    Route::put('majors/{id}/cycles/{cycleId}', [MajorController::class, 'saveCycle'])->whereNumber(['id', 'cycleId'])->name('majors.cycle');
});

// High schools (task 1.8). Authorization: HighSchoolPolicy / SchoolContactPolicy + data scope.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('high-schools', [HighSchoolController::class, 'index'])->name('high-schools.index');
    Route::post('high-schools', [HighSchoolController::class, 'store'])->name('high-schools.store');
    Route::post('high-schools/import', [HighSchoolController::class, 'import'])->name('high-schools.import');
    Route::get('high-schools/{id}', [HighSchoolController::class, 'show'])->whereNumber('id')->name('high-schools.show');
    Route::put('high-schools/{id}', [HighSchoolController::class, 'update'])->whereNumber('id')->name('high-schools.update');
    Route::delete('high-schools/{id}', [HighSchoolController::class, 'destroy'])->whereNumber('id')->name('high-schools.destroy');
    Route::post('high-schools/{schoolId}/contacts', [SchoolContactController::class, 'store'])->whereNumber('schoolId')->name('high-schools.contacts.store');
    Route::put('high-schools/{schoolId}/contacts/{contactId}', [SchoolContactController::class, 'update'])->whereNumber(['schoolId', 'contactId'])->name('high-schools.contacts.update');
    Route::delete('high-schools/{schoolId}/contacts/{contactId}', [SchoolContactController::class, 'destroy'])->whereNumber(['schoolId', 'contactId'])->name('high-schools.contacts.destroy');
});

// Campaigns (task 1.9). Authorization: CampaignPolicy (approval flow, closed cycle read-only).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{id}', [CampaignController::class, 'show'])->whereNumber('id')->name('campaigns.show');
    Route::put('campaigns/{id}', [CampaignController::class, 'update'])->whereNumber('id')->name('campaigns.update');
    Route::delete('campaigns/{id}', [CampaignController::class, 'destroy'])->whereNumber('id')->name('campaigns.destroy');
    Route::post('campaigns/{id}/transition', [CampaignController::class, 'transition'])->whereNumber('id')->name('campaigns.transition');
});

// Applicants (task 1.10). Authorization: ApplicantPolicy + data scope; phone / citizen id masked without view_sensitive.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('applicants', [ApplicantController::class, 'index'])->name('applicants.index');
    Route::post('applicants', [ApplicantController::class, 'store'])->name('applicants.store');
    Route::get('applicants/check-duplicate', [ApplicantController::class, 'checkDuplicate'])->name('applicants.check-duplicate');
    Route::get('applicants/{uuid}', [ApplicantController::class, 'show'])->whereUuid('uuid')->name('applicants.show');
    Route::put('applicants/{uuid}', [ApplicantController::class, 'update'])->whereUuid('uuid')->name('applicants.update');
    Route::delete('applicants/{uuid}', [ApplicantController::class, 'destroy'])->whereUuid('uuid')->name('applicants.destroy');
    Route::post('applicants/{uuid}/guardians', [ApplicantGuardianController::class, 'store'])->whereUuid('uuid')->name('applicants.guardians.store');
    Route::put('applicants/{uuid}/guardians/{guardianId}', [ApplicantGuardianController::class, 'update'])->whereUuid('uuid')->whereNumber('guardianId')->name('applicants.guardians.update');
    Route::delete('applicants/{uuid}/guardians/{guardianId}', [ApplicantGuardianController::class, 'destroy'])->whereUuid('uuid')->whereNumber('guardianId')->name('applicants.guardians.destroy');
    Route::put('applicants/{uuid}/scores', [ApplicantScoreController::class, 'update'])->whereUuid('uuid')->name('applicants.scores.update');
    Route::post('applicants/{uuid}/certificates', [ApplicantCertificateController::class, 'store'])->whereUuid('uuid')->name('applicants.certificates.store');
    Route::put('applicants/{uuid}/certificates/{certificateId}', [ApplicantCertificateController::class, 'update'])->whereUuid('uuid')->whereNumber('certificateId')->name('applicants.certificates.update');
    Route::delete('applicants/{uuid}/certificates/{certificateId}', [ApplicantCertificateController::class, 'destroy'])->whereUuid('uuid')->whereNumber('certificateId')->name('applicants.certificates.destroy');
});

// Admission cases (task 1.12). Authorization: CasePolicy + data scope; the rules are in the Admission actions.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('cases/table', [CaseController::class, 'table'])->name('cases.table');
    Route::get('cases/column', [CaseController::class, 'column'])->name('cases.column');
    Route::post('cases', [CaseController::class, 'store'])->name('cases.store');
    Route::post('cases/bulk', [CaseController::class, 'bulk'])->name('cases.bulk');
    Route::get('cases/{uuid}', [CaseController::class, 'show'])->whereUuid('uuid')->name('cases.show');
    Route::put('cases/{uuid}', [CaseController::class, 'update'])->whereUuid('uuid')->name('cases.update');
    Route::post('cases/{uuid}/move', [CaseController::class, 'move'])->whereUuid('uuid')->name('cases.move');
    Route::post('cases/{uuid}/assign', [CaseController::class, 'assign'])->whereUuid('uuid')->name('cases.assign');
    Route::post('cases/{uuid}/claim', [CaseController::class, 'claim'])->whereUuid('uuid')->name('cases.claim');
});

// Timeline, tasks, appointments (task 1.13). Authorization: the policies + data scope of each record.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('timeline', [TimelineController::class, 'index'])->name('timeline.index');
    Route::post('timeline', [TimelineController::class, 'store'])->name('timeline.store');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('tasks/{id}', [TaskController::class, 'update'])->whereNumber('id')->name('tasks.update');
    Route::post('tasks/{id}/status', [TaskController::class, 'status'])->whereNumber('id')->name('tasks.status');
    Route::delete('tasks/{id}', [TaskController::class, 'destroy'])->whereNumber('id')->name('tasks.destroy');

    Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::put('appointments/{id}', [AppointmentController::class, 'update'])->whereNumber('id')->name('appointments.update');
    Route::post('appointments/{id}/attendance', [AppointmentController::class, 'attendance'])->whereNumber('id')->name('appointments.attendance');
    Route::delete('appointments/{id}', [AppointmentController::class, 'destroy'])->whereNumber('id')->name('appointments.destroy');
});

// Header cycle selector (task 1.5): remembers the choice in the session.
Route::post('cycle/select', CycleSelectionController::class)->middleware(['auth', 'verified'])->name('cycle.select');

/*
|--------------------------------------------------------------------------
| CRM menu screens not built yet
|--------------------------------------------------------------------------
|
| Every config/navigation.php entry flagged `placeholder` gets its route
| (same URL and name the real screen will use) showing "Đang phát triển".
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    foreach (app(NavigationMenu::class)->placeholders() as $screen) {
        $breadcrumbs = array_values(array_filter([
            $screen['section'] !== null ? ['title' => $screen['section']] : null,
            ['title' => $screen['title'], 'href' => $screen['url']],
        ]));

        Route::inertia(ltrim($screen['url'], '/'), 'UnderConstruction', [
            'title' => $screen['title'],
            'phase' => $screen['phase'],
            'breadcrumbs' => $breadcrumbs,
        ])->name($screen['route']);
    }
});

/*
|--------------------------------------------------------------------------
| Shared platform endpoints
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // <EntityPicker> async search (sources in config/lookups.php).
    Route::get('lookups/{entity}', LookupController::class)
        ->where('entity', '[a-z_]+')
        ->middleware('throttle:120,1')
        ->name('lookups.show');

    // Component showcase, local/testing only.
    if (app()->environment(['local', 'testing'])) {
        Route::get('dev/components', ComponentsDemoController::class)->name('dev.components');
    }
});
