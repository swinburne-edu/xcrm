<?php

use App\Domain\Platform\Services\NavigationMenu;
use App\Http\Controllers\Platform\ComponentsDemoController;
use App\Http\Controllers\Platform\LookupController;
use Illuminate\Support\Facades\Route;

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
