<?php

use App\Http\Controllers\Intake\LeadWebhookController;
use App\Http\Controllers\Intake\PublicLeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API (anonymous; called by the web forms embedded on the university site)
|--------------------------------------------------------------------------
*/

Route::post('public/leads', PublicLeadController::class)->middleware('throttle:public-leads')->name('public.leads');

// Lead ads webhooks (task 2.7): Facebook Lead Ads, TikTok. The signature is checked in the controller.
Route::get('webhooks/leads/{uuid}', [LeadWebhookController::class, 'verify'])->whereUuid('uuid')->middleware('throttle:lead-webhooks')->name('webhooks.leads.verify');
Route::post('webhooks/leads/{uuid}', [LeadWebhookController::class, 'receive'])->whereUuid('uuid')->middleware('throttle:lead-webhooks')->name('webhooks.leads.receive');
