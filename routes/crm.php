<?php

use App\Domain\Platform\Services\NavigationMenu;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Platform\ComponentsDemoController;
use App\Http\Controllers\Platform\LookupController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\TeamController;
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
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::put('teams/{id}', [TeamController::class, 'update'])->whereNumber('id')->name('teams.update');
    Route::delete('teams/{id}', [TeamController::class, 'destroy'])->whereNumber('id')->name('teams.destroy');
});

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
