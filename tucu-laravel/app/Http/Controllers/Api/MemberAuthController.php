<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterMemberRequest;
use App\Models\Member;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MemberAuthController extends Controller
{
    /**
     * POST /api/members/register
     *
     * Registers a new member and immediately creates their personal portal
     * by issuing an auth token, so the frontend can redirect straight to
     * member.html after signup.
     */
    public function register(RegisterMemberRequest $request)
    {
        $validated = $request->validated();

        $member = Member::create([
            'full_name'     => $validated['full_name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'reg_number'    => $validated['reg_number'] ?? null,
            'phone'         => $validated['phone'] ?? null,
            'course'        => $validated['course'] ?? null,
            'year_of_study' => $validated['year_of_study'] ?? null,
            'ministry'      => $validated['ministry'] ?? 'General',
            'joined_at'     => now(),
        ]);

        // Every member gets a (default: subscribed) newsletter record,
        // so the dashboard can show subscription status immediately.
        NewsletterSubscriber::firstOrCreate(
            ['email' => $member->email],
            ['member_id' => $member->id, 'is_subscribed' => true, 'subscribed_at' => now()]
        );

        $token = $member->createToken('member-portal')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Welcome to TUCU! Your member portal has been created.',
            'token'   => $token,
            'member'  => $member,
        ], 201);
    }

    /**
     * POST /api/members/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $member = Member::where('email', $request->email)->first();

        if (! $member || ! Hash::check($request->password, $member->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($member->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact the CU leadership.'],
            ]);
        }

        $token = $member->createToken('member-portal')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'member'  => $member,
        ]);
    }

    /**
     * POST /api/members/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }
}
