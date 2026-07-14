<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrayerRequest;
use Illuminate\Http\Request;

class PrayerRequestController extends Controller
{
    /**
     * POST /api/prayer
     *
     * Public endpoint. If the request is made with a valid Sanctum token
     * (member is logged in), the prayer request is automatically linked
     * to their account so it appears in their personal portal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['nullable', 'string', 'max:255'],
            'course'       => ['nullable', 'string', 'max:255'],
            'category'     => ['required', 'string', 'max:100'],
            'request'      => ['required', 'string'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        // Optional auth: attach to logged-in member if a valid token is present
        $member = $request->user('sanctum');

        $prayer = PrayerRequest::create([
            'member_id'    => $member?->id,
            'name'         => ($validated['is_anonymous'] ?? false)
                                ? 'Anonymous'
                                : ($validated['name'] ?? $member?->full_name ?? 'Anonymous'),
            'course'       => $validated['course'] ?? $member?->course,
            'category'     => $validated['category'],
            'request'      => $validated['request'],
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your prayer request has been received. Our prayer team will intercede for you.',
            'prayer'  => $prayer,
        ], 201);
    }
}
