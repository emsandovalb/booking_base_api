<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
