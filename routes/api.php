<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ResourceStaffController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ReservationController;

/**
 * ALIASES SIN VERSIÓN (compatibilidad con cliente viejo)
 * Si tu app llama /api/login (sin /auth), deja también ese alias exacto.
 */
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);
Route::get('/translations', [TranslationController::class, 'index']);

// Si tu app usa exactamente /api/login:
Route::post('/login', [AuthController::class, 'login']);

// (Opcional) Lecturas públicas que tu app ya consuma sin versión:
Route::apiResource('/public/courts', CourtController::class)->only(['index', 'show'])->names('public.courts');
Route::get('/public/courts/{court}/availability', [CourtController::class, 'availability']);
Route::apiResource('/public/events', EventController::class)->only(['index', 'show'])->names('public.events');
Route::get('/tournaments', [TournamentController::class, 'index']);
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);

/**
 * TU GRUPO VERSIONADO EXISTENTE
 */
Route::prefix('v1')->group(function () {
    // Generic aliases for the base booking product.
    // Temporary compatibility note: courts/bookings remain the internal persistence names for now.
    Route::get('/resources', [ResourceController::class, 'index']);
    Route::get('/resources/{resource}', [ResourceController::class, 'show']);
    Route::get('/resources/{resource}/availability', [ResourceController::class, 'availability']);
    Route::get('/resources/{resource}/staff', [ResourceStaffController::class, 'index']);
    Route::get('/staff/roles', [StaffController::class, 'roles']);
    Route::get('/staff', [StaffController::class, 'index']);
    Route::get('/staff/{staff}', [StaffController::class, 'show']);

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);

    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
    Route::get('/tournaments/{tournament}/teams', [TournamentController::class, 'teams']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/profile', [AuthController::class, 'update']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/password/change', [AuthController::class, 'changePassword']);

        Route::get('/my/resources', [ResourceController::class, 'mine']);
        Route::post('/resources', [ResourceController::class, 'store']);
        Route::match(['put', 'patch'], '/resources/{resource}', [ResourceController::class, 'update']);
        Route::delete('/resources/{resource}', [ResourceController::class, 'destroy']);

        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
        Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
        Route::post('/reservations/{reservation}/rebook', [ReservationController::class, 'rebook']);

        Route::post('/staff', [StaffController::class, 'store']);
        Route::match(['put', 'patch'], '/staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
        Route::patch('/staff/{staff}/deactivate', [StaffController::class, 'deactivate']);
        Route::post('/staff/{staff}/services', [StaffController::class, 'attachService']);
        Route::delete('/staff/{staff}/services/{resourceId}', [StaffController::class, 'detachService']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/bookings/{booking}/rebook', [BookingController::class, 'rebook']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        // Admin only
        Route::get('/my/grounds', [CourtController::class, 'mine']);
        Route::post('/courts', [CourtController::class, 'store']);
        Route::put('/courts/{court}', [CourtController::class, 'update']);
        Route::delete('/courts/{court}', [CourtController::class, 'destroy']);
        Route::get('/admin/reservations', [CourtController::class, 'reservationsForDay']);

        Route::post('/events', [EventController::class, 'store']);
        Route::post('/tournaments', [TournamentController::class, 'store']);
        Route::put('/tournaments/{tournament}', [TournamentController::class, 'update']);
        Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy']);
        Route::post('/tournaments/{tournament}/teams', [TournamentController::class, 'enrollTeam']);
        Route::put('/tournaments/{tournament}/teams/{team}', [TournamentController::class, 'updateEnrollment']);

        Route::get('/my/teams', [TeamController::class, 'index']);
        Route::post('/my/teams', [TeamController::class, 'store']);
        Route::put('/my/teams/{team}', [TeamController::class, 'update']);
    });

    Route::apiResource('courts', CourtController::class)->only(['index', 'show']);
    Route::get('/courts/{court}/availability', [CourtController::class, 'availability']);
    Route::apiResource('events', EventController::class)->only(['index', 'show']);
});
