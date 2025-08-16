<?php

namespace App\Http\Controllers;

use App\Models\DoorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoorEventController extends Controller
{
    // GET /api/door-events
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        return response()->json(
            DoorEvent::orderByDesc('occurred_at')->paginate($perPage)
        );
    }

    // POST /api/door-events
    public function store(Request $request)
    {
        $data = $request->validate([
            'reason'      => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'], // default now()
        ]);

        $user = Auth::user();
        $event = DoorEvent::create([
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'reason'      => $data['reason'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        return response()->json($event, 201);
    }

    // GET /api/door-events/{id}
    public function show($id)
    {
        $event = DoorEvent::findOrFail($id);
        return response()->json($event);
    }

    // PUT/PATCH /api/door-events/{id}
    public function update(Request $request, $id)
    {
        $event = DoorEvent::findOrFail($id);

        $data = $request->validate([
            'reason'      => ['nullable', 'string'],
            'occurred_at' => ['sometimes', 'date'],
        ]);

        $event->update($data);
        return response()->json($event);
    }

    // DELETE /api/door-events/{id}
    public function destroy($id)
    {
        $event = DoorEvent::findOrFail($id);
        $event->delete(); // soft delete
        return response()->json(['message' => 'Door event deleted']);
    }
}
