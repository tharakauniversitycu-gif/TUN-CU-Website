<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NewsletterController extends Controller
{
    /**
     * POST /api/newsletter/subscribe
     * Public endpoint for the footer subscribe form.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $member = $request->user('sanctum');

        $existing = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($existing) {
            if ($existing->is_subscribed) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already subscribed.'],
                ]);
            }
            $existing->update(['is_subscribed' => true, 'subscribed_at' => now()]);
            return response()->json([
                'success' => true,
                'message' => 'Welcome back! You have been re-subscribed.',
            ], 201);
        }

        NewsletterSubscriber::create([
            'member_id'     => $member?->id,
            'email'         => $validated['email'],
            'is_subscribed' => true,
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscribed! You will receive weekly devotionals and updates.',
        ], 201);
    }
}
