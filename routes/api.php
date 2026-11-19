<?php

use App\Http\Controllers\Api\V1\ApplicantApiController;
use App\Http\Controllers\Api\V1\ApplicationApiController;
use App\Http\Controllers\Api\V1\CaseApiController;
use App\Http\Controllers\Intake\CallWebhookController;
use App\Http\Controllers\Intake\LeadWebhookController;
use App\Http\Controllers\Intake\PublicLeadController;
use App\Http\Middleware\ApiAbility;
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

// Call centre webhook (task 3.7): ringing / answered / ended events of the telephone system, signed with HMAC.
Route::post('webhooks/calls/{uuid}', CallWebhookController::class)->whereUuid('uuid')->middleware('throttle:lead-webhooks')->name('webhooks.calls');

// REST API (task 3.5): bearer token of a user (Sanctum); the token acts as that user, so permissions and data scope are the same as on the screens.
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('me', fn (\Illuminate\Http\Request $request) => response()->json(['data' => ['id' => $request->user()->id, 'name' => $request->user()->name, 'email' => $request->user()->email]]))->middleware(ApiAbility::class.':read');

    Route::middleware(ApiAbility::class.':read')->group(function () {
        Route::get('applicants', [ApplicantApiController::class, 'index']);
        Route::get('applicants/{uuid}', [ApplicantApiController::class, 'show'])->whereUuid('uuid');
        Route::get('cases', [CaseApiController::class, 'index']);
        Route::get('cases/{uuid}', [CaseApiController::class, 'show'])->whereUuid('uuid');
        Route::get('applications', [ApplicationApiController::class, 'index']);
        Route::get('applications/{id}', [ApplicationApiController::class, 'show'])->whereNumber('id');
    });

    Route::middleware(ApiAbility::class.':write')->group(function () {
        Route::post('applicants', [ApplicantApiController::class, 'store']);
        Route::patch('applicants/{uuid}', [ApplicantApiController::class, 'update'])->whereUuid('uuid');
        Route::post('cases/{uuid}/stage', [CaseApiController::class, 'stage'])->whereUuid('uuid');
        Route::post('cases/{uuid}/applications', [ApplicationApiController::class, 'store'])->whereUuid('uuid');
        Route::post('applications/{id}/{action}', [ApplicationApiController::class, 'transition'])->whereNumber('id');
    });
});
