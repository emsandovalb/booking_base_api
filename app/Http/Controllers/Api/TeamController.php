<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $teams = Team::query()
            ->with('owner')
            ->withCount(['users', 'tournaments'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('users', function ($memberQuery) use ($user) {
                        $memberQuery->where('users.id', $user->id)
                            ->wherePivotIn('role', ['owner', 'captain', 'manager']);
                    });
            })
            ->latest()
            ->get();

        return response()->json(['data' => $teams]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:4096',
        ]);

        $this->storeLogo($request, $data);

        $team = Team::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'city' => $data['city'] ?? null,
            'logo' => $data['logo'] ?? null,
            'status' => 'active',
        ]);

        $team->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        return response()->json([
            'message' => 'Team created',
            'team' => $team->fresh(['owner'])->loadCount(['users', 'tournaments']),
        ], 201);
    }

    public function update(Request $request, Team $team)
    {
        if (!$this->canManageTeam($request, $team)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'city' => 'sometimes|nullable|string|max:255',
            'logo' => 'nullable|image|max:4096',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $this->storeLogo($request, $data, $team);

        $team->fill($data);
        $team->save();

        return response()->json([
            'message' => 'Team updated',
            'team' => $team->fresh(['owner'])->loadCount(['users', 'tournaments']),
        ]);
    }

    private function canManageTeam(Request $request, Team $team): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        if ((int) $team->user_id === (int) $user->id) {
            return true;
        }

        return $team->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['owner', 'captain', 'manager'])
            ->exists();
    }

    private function storeLogo(Request $request, array &$data, ?Team $existing = null): void
    {
        if (!$request->hasFile('logo')) {
            return;
        }

        if ($existing && $existing->logo) {
            Storage::disk('public')->delete($existing->logo);
        }

        $data['logo'] = $request->file('logo')->store('teams', 'public');
    }
}
