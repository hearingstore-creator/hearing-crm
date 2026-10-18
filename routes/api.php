<?php

use App\Http\Controllers\Api\PublicAppointmentController;
use App\Http\Controllers\Api\WebLeadController;
use Illuminate\Support\Facades\Route;

// Website lead capture – server-to-server, HMAC signed (see WebLeadController)
Route::post('v1/leads', [WebLeadController::class, 'store'])->name('api.leads.store');

// Public Website Appointment Booking API
Route::prefix('v1/appointments')->group(function () {
    Route::get('branches', [PublicAppointmentController::class, 'branches'])->name('api.appointments.branches');
    Route::get('services', [PublicAppointmentController::class, 'services'])->name('api.appointments.services');
    Route::get('availability', [PublicAppointmentController::class, 'availability'])->name('api.appointments.availability');
    Route::post('hold', [PublicAppointmentController::class, 'hold'])->name('api.appointments.hold')->middleware('throttle:30,1');
    Route::post('book', [PublicAppointmentController::class, 'book'])->name('api.appointments.book')->middleware('throttle:10,1');
});
