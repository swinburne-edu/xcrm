<?php

use App\Domain\Platform\Services\NavigationMenu;
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
