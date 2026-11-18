<?php

use App\Http\Controllers\Intake\PublicLeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API (anonymous; called by the web forms embedded on the university site)
|--------------------------------------------------------------------------
*/

Route::post('public/leads', PublicLeadController::class)->middleware('throttle:public-leads')->name('public.leads');
