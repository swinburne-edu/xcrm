<?php

use App\Http\Controllers\HighSchool\CheckinController;
use App\Http\Controllers\Report\DashboardController;
use Illuminate\Support\Facades\Route;

// The entry point is the login page; a signed-in user is bounced on to the dashboard by the guest middleware.
Route::redirect('/', '/login')->name('home');

// Public check-in page behind the QR code of a school activity (task 2.1): anonymous, rate limited.
Route::get('checkin/{token}', [CheckinController::class, 'show'])->where('token', '[A-Za-z0-9]{20,64}')->name('checkin.show');
Route::post('checkin/{token}', [CheckinController::class, 'store'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:checkin')->name('checkin.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/crm.php';
require __DIR__.'/settings.php';
