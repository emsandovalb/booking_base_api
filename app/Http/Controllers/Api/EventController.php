<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        return Event::latest()->paginate(20);
    }

    public function show(Event $event)
    {
        return $event;
    }

    public function store(Request $request)
    {
        // Events have no business_id — they're platform-wide, not
        // tenant-scoped (see decision note below). There's no specific
        // business to check membership against, so this is gated by the
        // platform Super Admin flag, not canManageBusiness().
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => 'required',
            'description' => 'nullable',
            'price' => 'nullable|numeric',
            'date' => 'nullable|date',
        ]);
        $event = Event::create($data);
        return response()->json($event, 201);
    }
}

