<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    /**
     * GET /api/events
     * Public list of upcoming events (used to populate the events section).
     */
    public function index()
    {
        $events = Event::orderBy('starts_at')->get();

        return response()->json(['success' => true, 'events' => $events]);
    }

    /**
     * POST /api/events/register
     *
     * Public endpoint. If the request carries a valid Sanctum token,
     * the registration is linked to that member's account and will
     * appear in their personal portal's event history.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'event_id'   => ['nullable', 'integer'],
            'event_name' => ['required', 'string', 'max:255'],
            'full_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'reg_number' => ['nullable', 'string', 'max:50'],
        ]);

        $member = $request->user('sanctum');

        // Prevent duplicate registration for the same event + email
        $existing = EventRegistration::where('email', $validated['email'])
            ->where('event_name', $validated['event_name'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'email' => ['You are already registered for this event.'],
            ]);
        }

        $registration = EventRegistration::create([
            'event_id'      => $validated['event_id'] ?? null,
            'event_name'    => $validated['event_name'],
            'member_id'     => $member?->id,
            'full_name'     => $validated['full_name'],
            'email'         => $validated['email'],
            'phone'         => $validated['phone'] ?? $member?->phone,
            'reg_number'    => $validated['reg_number'] ?? $member?->reg_number,
            'registered_at' => now(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => "You're registered for {$validated['event_name']}! See you there.",
            'registration' => $registration,
        ], 201);
    }
}
