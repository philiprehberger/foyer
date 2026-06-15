<?php

use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Api\BusinessesController;
use App\Http\Controllers\Api\ConversationsController;
use App\Http\Controllers\Api\GoogleCalendarPushController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NumbersController;
use App\Http\Controllers\Api\OutOfScopeLogController;
use App\Http\Controllers\Api\SmsInboundController;
use App\Http\Controllers\Api\TwilioStatusController;
use App\Http\Controllers\Api\WebInboundController;
use App\Http\Controllers\Api\WebSessionController;
use App\Http\Controllers\Internal\AgentEscalateController;
use App\Http\Controllers\Internal\AgentTurnContextController;
use App\Http\Controllers\Internal\AgentTurnResultController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('healthz', HealthController::class)->name('v1.healthz');

    // Public webhook endpoints — signature is the auth.
    Route::post('sms/inbound', SmsInboundController::class)
        ->middleware('twilio.sig')
        ->name('v1.sms.inbound');
    Route::post('twilio/status', TwilioStatusController::class)
        ->middleware('twilio.sig')
        ->name('v1.twilio.status');
    Route::post('google/calendar-push', GoogleCalendarPushController::class)
        ->name('v1.google.calendar-push');

    // Web widget — session-token-bound, separate channel.
    Route::post('web/sessions', [WebSessionController::class, 'store'])->name('v1.web.sessions.store');
    Route::post('web/sessions/{session}/verify-phone',
        [WebSessionController::class, 'verifyPhone'])->name('v1.web.sessions.verify-phone');
    Route::post('web/inbound', WebInboundController::class)->name('v1.web.inbound');

    // Owner-authenticated surface.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('conversations', [ConversationsController::class, 'index'])
            ->name('v1.conversations.index');
        Route::get('conversations/{conversation}', [ConversationsController::class, 'show'])
            ->name('v1.conversations.show');

        Route::get('bookings', [BookingsController::class, 'index'])->name('v1.bookings.index');
        Route::get('bookings/{booking}', [BookingsController::class, 'show'])->name('v1.bookings.show');

        Route::post('bookings/{booking}/confirm', [BookingsController::class, 'confirm'])
            ->middleware('idempotency')
            ->name('v1.bookings.confirm');
        Route::post('bookings/{booking}/reject', [BookingsController::class, 'reject'])
            ->middleware('idempotency')
            ->name('v1.bookings.reject');

        Route::patch('businesses/{business}/scope', [BusinessesController::class, 'updateScope'])
            ->name('v1.businesses.scope');
        Route::post('businesses/{business}/kill-switch', [BusinessesController::class, 'toggleKillSwitch'])
            ->name('v1.businesses.kill-switch');

        Route::get('out-of-scope-log', OutOfScopeLogController::class)->name('v1.out-of-scope-log');

        Route::post('numbers/provision', [NumbersController::class, 'provision'])
            ->name('v1.numbers.provision');
        Route::get('jobs/{job}', [NumbersController::class, 'jobStatus'])->name('v1.jobs.show');
    });
});

// Internal API — FastAPI worker <-> Laravel. Loopback only + HMAC.
Route::prefix('_internal')
    ->middleware(['internal.loopback', 'internal.hmac'])
    ->group(function () {
        Route::get('conversations/{conversation}/turn-context', AgentTurnContextController::class)
            ->name('internal.agent.turn-context');
        Route::post('conversations/{conversation}/turn-result', AgentTurnResultController::class)
            ->name('internal.agent.turn-result');
        Route::post('conversations/{conversation}/escalate', AgentEscalateController::class)
            ->name('internal.agent.escalate');
    });
