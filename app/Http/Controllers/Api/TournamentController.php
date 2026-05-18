<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TournamentTeam;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::with(['organizer', 'court'])
            ->withCount('teams')
            ->latest();

        $paginator = $query->paginate($this->perPageFromRequest($request))->withQueryString();

        return $this->paginatedResponse($paginator);
    }

    public function show(Request $request, Tournament $tournament)
    {
        return $tournament->load(['organizer', 'court'])->loadCount('teams');
    }

    public function teams(Request $request, Tournament $tournament)
    {
        $teams = $tournament->teams()
            ->with('owner')
            ->orderByPivot('registered_at', 'desc')
            ->get();

        return response()->json(['data' => $teams]);
    }

    public function enrollTeam(Request $request, Tournament $tournament)
    {
        if ($this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'team_id' => 'required|exists:teams,id',
        ]);

        if ($this->tournamentClosed($tournament)) {
            return response()->json(['message' => 'Tournament is closed'], 422);
        }

        $team = Team::with('users')->findOrFail($data['team_id']);

        if (!$this->canManageTeam($request, $team)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (TournamentTeam::where('tournament_id', $tournament->id)
            ->where('team_id', $team->id)
            ->exists()) {
            return response()->json(['message' => 'Team is already enrolled in this tournament'], 422);
        }

        $enrolledCount = TournamentTeam::where('tournament_id', $tournament->id)
            ->whereIn('status', ['pending', 'approved'])
            ->count();
        if ($tournament->max_teams !== null && $enrolledCount >= $tournament->max_teams) {
            return response()->json(['message' => 'Tournament is full'], 422);
        }

        $enrollment = TournamentTeam::create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
            'registered_at' => now(),
        ]);

        return response()->json([
            'message' => 'Team enrolled',
            'enrollment' => $enrollment->load(['team.owner', 'tournament']),
            'tournament' => $tournament->fresh(['organizer', 'court'])->loadCount('teams'),
        ], 201);
    }

    public function updateEnrollment(Request $request, Tournament $tournament, Team $team)
    {
        if (!$this->canManageTournament($request, $tournament)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'withdrawn'])],
        ]);

        $enrollment = TournamentTeam::where('tournament_id', $tournament->id)
            ->where('team_id', $team->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Enrollment not found'], 404);
        }

        $enrollment->status = $data['status'];
        $enrollment->save();

        return response()->json([
            'message' => 'Enrollment updated',
            'enrollment' => $enrollment->fresh(['team.owner', 'tournament']),
            'tournament' => $tournament->fresh(['organizer', 'court'])->loadCount('teams'),
        ]);
    }

    public function store(Request $request)
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:4096',
            'format' => 'nullable|string|max:50',
            'court_id' => 'nullable|exists:courts,id',
            'entry_fee' => 'nullable|numeric|min:0',
            'prize_pool' => 'nullable|numeric|min:0',
            'max_teams' => 'nullable|integer|min:2|max:256',
            'registration_deadline' => 'nullable|date',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'rules' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        $this->storeCoverImage($request, $data);

        $tournament = Tournament::create(array_merge($data, [
            'user_id' => $request->user()->id,
            'status' => 'draft',
        ]));

        return response()->json($tournament->fresh(['organizer', 'court'])->loadCount('teams'), 201);
    }

    public function update(Request $request, Tournament $tournament)
    {
        if (!$this->canManageTournament($request, $tournament)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'cover_image' => 'nullable|image|max:4096',
            'format' => 'sometimes|nullable|string|max:50',
            'court_id' => 'sometimes|nullable|exists:courts,id',
            'entry_fee' => 'sometimes|nullable|numeric|min:0',
            'prize_pool' => 'sometimes|nullable|numeric|min:0',
            'max_teams' => 'sometimes|nullable|integer|min:2|max:256',
            'registration_deadline' => 'sometimes|nullable|date',
            'starts_at' => 'sometimes|nullable|date',
            'ends_at' => 'sometimes|nullable|date|after_or_equal:starts_at',
            'rules' => 'sometimes|nullable|string',
            'settings' => 'sometimes|nullable|array',
        ]);

        $this->storeCoverImage($request, $data, $tournament);

        $tournament->fill($data);
        $tournament->save();

        return response()->json($tournament->fresh(['organizer', 'court'])->loadCount('teams'));
    }

    public function destroy(Request $request, Tournament $tournament)
    {
        if (!$this->canManageTournament($request, $tournament)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tournament->status = 'inactive';
        $tournament->save();

        return response()->json([
            'message' => 'Tournament closed',
            'tournament' => $tournament->fresh(['organizer', 'court'])->loadCount('teams'),
        ]);
    }

    private function canManageTournament(Request $request, Tournament $tournament): bool
    {
        return $this->isAdmin($request);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->role === 'admin';
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

    private function tournamentClosed(Tournament $tournament): bool
    {
        if ($tournament->status === 'inactive') {
            return true;
        }

        if ($tournament->registration_deadline && now()->greaterThan($tournament->registration_deadline)) {
            return true;
        }

        return false;
    }

    private function storeCoverImage(Request $request, array &$data, ?Tournament $existing = null): void
    {
        if (!$request->hasFile('cover_image')) {
            return;
        }

        if ($existing && $existing->cover_image) {
            Storage::disk('public')->delete($existing->cover_image);
        }

        $data['cover_image'] = $request->file('cover_image')->store('tournaments', 'public');
    }

    private function perPageFromRequest(Request $request): int
    {
        $per = (int) $request->query('per_page', 20);
        if ($per < 1) {
            $per = 1;
        }
        if ($per > 50) {
            $per = 50;
        }
        return $per;
    }

    private function paginatedResponse($paginator)
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }
}
