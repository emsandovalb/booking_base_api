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

