<?php

use App\Domain\Platform\Services\NavigationMenu;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Cycle\CycleController;
use App\Http\Controllers\Cycle\CycleMethodController;
use App\Http\Controllers\Cycle\CycleRoundController;
use App\Http\Controllers\Cycle\CycleSelectionController;
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
    Route::post('cycles/{id}/reopen', [CycleController::class, 'reopen'])->whereNumber('id')->name('cycles.reopen');
    Route::post('cycles/{cycleModel}/rounds', [CycleRoundController::class, 'store'])->whereNumber('cycleModel')->name('cycles.rounds.store');
    Route::put('cycles/{cycleModel}/rounds/{roundId}', [CycleRoundController::class, 'update'])->whereNumber(['cycleModel', 'roundId'])->name('cycles.rounds.update');
    Route::delete('cycles/{cycleModel}/rounds/{roundId}', [CycleRoundController::class, 'destroy'])->whereNumber(['cycleModel', 'roundId'])->name('cycles.rounds.destroy');
    Route::post('cycles/{cycleModel}/methods', [CycleMethodController::class, 'store'])->whereNumber('cycleModel')->name('cycles.methods.store');
    Route::put('cycles/{cycleModel}/methods/{methodId}', [CycleMethodController::class, 'update'])->whereNumber(['cycleModel', 'methodId'])->name('cycles.methods.update');
    Route::delete('cycles/{cycleModel}/methods/{methodId}', [CycleMethodController::class, 'destroy'])->whereNumber(['cycleModel', 'methodId'])->name('cycles.methods.destroy');

    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::put('teams/{id}', [TeamController::class, 'update'])->whereNumber('id')->name('teams.update');
    Route::delete('teams/{id}', [TeamController::class, 'destroy'])->whereNumber('id')->name('teams.destroy');
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
