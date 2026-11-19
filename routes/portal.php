<?php

use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\EstimatorController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Middleware\PortalContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages and applicant portal (task 3.4)
|--------------------------------------------------------------------------
| No staff login: PortalContext picks the organization (config/portal.php); the group with ":auth" also needs a
| portal session (SMS code).
*/

// Score and major suggestion, also a lead form.
Route::middleware(PortalContext::class)->group(function () {
    Route::get('tuyen-sinh', [EstimatorController::class, 'show'])->name('portal.estimator');
    Route::post('tuyen-sinh/tinh-diem', [EstimatorController::class, 'estimate'])->middleware('throttle:portal-public')->name('portal.estimate');
    Route::post('tuyen-sinh/dang-ky', [EstimatorController::class, 'register'])->middleware('throttle:portal-public')->name('portal.register-lead');
});

// Login by SMS code.
Route::middleware(PortalContext::class)->prefix('cong-thi-sinh')->group(function () {
    Route::get('dang-nhap', [AuthController::class, 'show'])->name('portal.login');
    Route::post('ma-xac-thuc', [AuthController::class, 'requestCode'])->middleware('throttle:portal-otp')->name('portal.otp.request');
    Route::post('xac-thuc', [AuthController::class, 'verify'])->middleware('throttle:portal-otp')->name('portal.otp.verify');
    Route::post('dang-ky', [AuthController::class, 'register'])->middleware('throttle:portal-otp')->name('portal.register');
});

// Signed-in applicant.
Route::middleware(PortalContext::class.':auth')->prefix('cong-thi-sinh')->group(function () {
    Route::get('/', [PortalController::class, 'home'])->name('portal.home');
    Route::post('dang-xuat', [AuthController::class, 'logout'])->name('portal.logout');
    Route::put('diem', [PortalController::class, 'saveScores'])->name('portal.scores');
    Route::post('nguyen-vong', [PortalController::class, 'addApplication'])->name('portal.applications.store');
    Route::delete('nguyen-vong/{id}', [PortalController::class, 'destroy'])->whereNumber('id')->name('portal.applications.destroy');
    Route::post('nguyen-vong/{id}/{action}', [PortalController::class, 'transition'])->whereNumber('id')->name('portal.applications.transition');
});
