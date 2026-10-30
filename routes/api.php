<?php

use App\Http\Controllers\Api\PublicAppointmentController;
use App\Http\Controllers\Api\WebLeadController;
use Illuminate\Support\Facades\Route;

// Website lead capture – server-to-server, HMAC signed (see WebLeadController)
Route::post('v1/leads', [WebLeadController::class, 'store'])->name('api.leads.store');

// Lead connectors (see LeadHookController): Meta Lead Ads + WhatsApp, Google lead forms, any webhook
Route::get('v1/hooks/meta', [\App\Http\Controllers\Api\LeadHookController::class, 'metaVerify'])->name('api.hooks.meta.verify')->middleware('throttle:30,1,hook-meta-verify');
Route::post('v1/hooks/meta', [\App\Http\Controllers\Api\LeadHookController::class, 'meta'])->name('api.hooks.meta')->middleware('throttle:600,1,hook-meta');
Route::post('v1/hooks/google/{formKey}', [\App\Http\Controllers\Api\LeadHookController::class, 'google'])->name('api.hooks.google')->middleware('throttle:120,1,hook-google');
Route::post('v1/hooks/lead/{formKey}', [\App\Http\Controllers\Api\LeadHookController::class, 'generic'])->name('api.hooks.lead')->middleware('throttle:120,1,hook-lead');

// Replies to our SMS, pushed by the gateway. Switched off until SMS_INBOUND_SECRET is set (see SmsInboundController)
Route::match(['get', 'post'], 'v1/sms/inbound', [\App\Http\Controllers\Api\SmsInboundController::class, 'receive'])->name('api.sms.inbound')->middleware('throttle:120,1,sms-inbound');

// Public Website Appointment Booking API
// Lookups are public; hold/book are signed server-to-server calls with per-visitor limits in the controller.
// Each throttle has its own counter (3rd argument); the global 'api' limit is the overall ceiling.
Route::prefix('v1/appointments')->group(function () {
    Route::get('branches', [PublicAppointmentController::class, 'branches'])->name('api.appointments.branches')->middleware('throttle:120,1,appt-read');
    Route::get('services', [PublicAppointmentController::class, 'services'])->name('api.appointments.services')->middleware('throttle:120,1,appt-read');
    Route::get('availability', [PublicAppointmentController::class, 'availability'])->name('api.appointments.availability')->middleware('throttle:120,1,appt-read');
    Route::post('hold', [PublicAppointmentController::class, 'hold'])->name('api.appointments.hold')->middleware('throttle:60,1,appt-hold');
    Route::post('book', [PublicAppointmentController::class, 'book'])->name('api.appointments.book')->middleware('throttle:30,1,appt-book');
});
