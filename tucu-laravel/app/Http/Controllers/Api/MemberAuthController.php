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

        $membershipCategory = $validated['membership_category'] ?? 'full';
        $yearOfStudy = isset($validated['year_of_study']) ? (int) $validated['year_of_study'] : 1;
        $role = 'member';
        $status = 'active';
        $becameAssociateAt = null;

        if ($membershipCategory === 'associate' || $yearOfStudy === 0) {
            $membershipCategory = 'associate';
            $role = 'associate';
            $status = 'associate';
            $yearOfStudy = 0;
            $becameAssociateAt = now();
        }

        $member = Member::create([
            'full_name'           => $validated['full_name'],
            'email'               => $validated['email'],
            'password'            => Hash::make($validated['password']),
            'reg_number'          => $validated['reg_number'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'course'              => $validated['course'] ?? null,
            'course_duration'     => $validated['course_duration'] ?? 4,
            'year_of_study'       => $yearOfStudy,
            'membership_category' => $membershipCategory,
            'role'                => $role,
            'status'              => $status,
            'is_year_confirmed'   => true,
            'year_confirmed_at'   => now(),
            'became_associate_at' => $becameAssociateAt,
            'joined_at'           => now(),
        ]);

        // Attach up to 3 ministries
        if (! empty($validated['ministry_ids'])) {
            $member->ministries()->sync($validated['ministry_ids']);
        }

        // Record initial year progression entry
        $currentAcademicYear = \App\Models\AcademicYear::current();
        \App\Models\MemberYearHistory::create([
            'member_id'        => $member->id,
            'academic_year_id' => $currentAcademicYear?->id,
            'year_of_study'    => $yearOfStudy,
            'action'           => $membershipCategory === 'associate' ? 'joined_as_associate' : 'registered',
            'notes'            => 'Initial registration',
            'confirmed_at'     => now(),
        ]);

        // Every member gets a (default: subscribed) newsletter record
        NewsletterSubscriber::firstOrCreate(
            ['email' => $member->email],
            ['member_id' => $member->id, 'is_subscribed' => true, 'subscribed_at' => now()]
        );

        $member->load(['ministries.docket']);
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

        $member = Member::with(['ministries.docket'])->where('email', $request->email)->first();

        if (! $member || ! Hash::check($request->password, $member->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($member->status === 'inactive' || $member->status === 'suspended') {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently inactive or suspended. Please contact the CU leadership.'],
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
