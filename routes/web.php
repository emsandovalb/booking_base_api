<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin\BusinessController;
use App\Http\Controllers\SuperAdmin\BusinessMemberController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\WorkspaceController;
use App\Http\Controllers\Web\BusinessAppController;
use App\Http\Controllers\Web\PublicBookingController;
use App\Http\Controllers\Web\PwaController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/offline', fn () => view('pwa.offline'))->name('pwa.offline');
Route::get('/service-worker.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
Route::get('/pwa/{slug}/icon-{size}.svg', [PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');
Route::get('/{experience}/{slug}/manifest.webmanifest', [PwaController::class, 'manifest'])
    ->whereIn('experience', ['b', 'app'])->name('pwa.manifest');

Route::prefix('b/{slug}')->group(function () {
    Route::get('/', [PublicBookingController::class, 'show'])->name('booking.public');
    Route::get('/availability', [PublicBookingController::class, 'availability'])->name('booking.availability');
    Route::post('/book', [PublicBookingController::class, 'store'])->middleware('throttle:20,1')->name('booking.store');
    Route::get('/confirmed/{token}', [PublicBookingController::class, 'confirmed'])
        ->middleware('signed')->name('booking.confirmed');
});

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('app/{slug}')->middleware('auth')->name('business.')->group(function () {
    Route::get('/', [BusinessAppController::class, 'home'])->name('home');
    Route::get('/agenda', [BusinessAppController::class, 'agenda'])->name('agenda');
    Route::get('/bookings/{bookingId}', [BusinessAppController::class, 'booking'])->name('bookings.show');
    Route::post('/bookings/{bookingId}/{action}', [BusinessAppController::class, 'transition'])
        ->whereIn('action', ['confirm', 'cancel'])->name('bookings.transition');
    Route::post('/bookings/{bookingId}/reschedule', [BusinessAppController::class, 'reschedule'])->name('bookings.reschedule');
    Route::get('/services', [BusinessAppController::class, 'services'])->name('services');
    Route::post('/services', [BusinessAppController::class, 'storeService'])->name('services.store');
    Route::put('/services/{service}', [BusinessAppController::class, 'updateService'])->name('services.update');
    Route::get('/staff', [BusinessAppController::class, 'staff'])->name('staff');
    Route::post('/staff', [BusinessAppController::class, 'storeStaff'])->name('staff.store');
    Route::patch('/staff/{staff}/toggle', [BusinessAppController::class, 'toggleStaff'])->name('staff.toggle');
    Route::get('/share', [BusinessAppController::class, 'share'])->name('share');
});

/*
|--------------------------------------------------------------------------
| Password reset link landing route
|--------------------------------------------------------------------------
|
| This minimal route exists so that Laravel's built-in password reset
| notification can generate a valid URL using the named route
| "password.reset". The mobile app will handle the actual reset flow;
| this route simply confirms that the link is valid for now.
|
*/
Route::get('/reset-password/{token}', function (string $token) {
    return response()->json([
        'message' => 'Password reset link opened. Mobile reset UI is not yet implemented.',
        'token' => $token,
        'email' => request('email'),
    ]);
})->name('password.reset');

Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware(['auth', 'superadmin'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('/businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('/businesses/{business}/workspace', [WorkspaceController::class, 'show'])->name('businesses.workspace');
        Route::get('/businesses/{business}/members', [BusinessMemberController::class, 'index'])->name('businesses.members.index');
        Route::get('/businesses/{business}/members/create', [BusinessMemberController::class, 'create'])->name('businesses.members.create');
        Route::post('/businesses/{business}/members', [BusinessMemberController::class, 'store'])->name('businesses.members.store');
        Route::get('/businesses/{business}/members/{user}/edit', [BusinessMemberController::class, 'edit'])->name('businesses.members.edit');
        Route::put('/businesses/{business}/members/{user}', [BusinessMemberController::class, 'update'])->name('businesses.members.update');
        Route::patch('/businesses/{business}/members/{user}/status', [BusinessMemberController::class, 'status'])->name('businesses.members.status');
        Route::delete('/businesses/{business}/members/{user}', [BusinessMemberController::class, 'destroy'])->name('businesses.members.destroy');
        Route::get('/businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
        Route::get('/businesses/{business}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
        Route::match(['put', 'patch'], '/businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::patch('/businesses/{business}/activate', [BusinessController::class, 'activate'])->name('businesses.activate');
        Route::patch('/businesses/{business}/suspend', [BusinessController::class, 'suspend'])->name('businesses.suspend');
    });
