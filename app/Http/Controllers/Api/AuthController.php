<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->withManagedTeams($user)]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $this->withManagedTeams($user)]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $this->withManagedTeams($user);
        $user->avatar_url = $user->avatar ? Storage::url($user->avatar) : null;
        return $user;
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        // Keep backward compatibility: if first/last provided, update name too
        if (!empty($data['first_name']) || !empty($data['last_name'])) {
            $fn = $data['first_name'] ?? $user->first_name ?? '';
            $ln = $data['last_name'] ?? $user->last_name ?? '';
            $data['name'] = trim($fn.' '.$ln) ?: ($data['name'] ?? $user->name);
        }

        $user->fill($data);
        $user->save();
        $user->refresh();
        $this->withManagedTeams($user);
        $user->avatar_url = $user->avatar ? Storage::url($user->avatar) : null;
        return response()->json(['user' => $user]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }
        $user->password = Hash::make($data['new_password']);
        $user->save();
        return response()->json(['message' => 'Password updated']);
    }

    private function withManagedTeams(User $user): User
    {
        $ownedTeams = Team::query()
            ->where('user_id', $user->id)
            ->get();

        $memberTeams = $user->teams()->get();

        $merged = $ownedTeams
            ->concat($memberTeams)
            ->unique('id')
            ->values();

        $user->setRelation('teams', $merged);

        return $user;
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $status = Password::sendResetLink(['email' => $data['email']]);
        } catch (\Throwable $e) {
            Log::error('Password reset link failed', [
                'email' => $data['email'],
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to send password reset link at this time. Please try again later.',
            ], 500);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('Password reset link not sent', [
                'email' => $data['email'],
                'status' => $status,
            ]);

            return response()->json([
                'message' => __($status),
                'errors' => ['email' => [__($status)]],
            ], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? null,
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json([
            'message' => __($status),
            'errors' => ['email' => [__($status)]],
        ], 422);
    }
}
