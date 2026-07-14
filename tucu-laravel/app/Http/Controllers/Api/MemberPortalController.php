<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MemberPortalController extends Controller
{
    /**
     * Ensure the authenticated member can only ever access their own
     * record. Returns the Member or aborts with 403.
     */
    private function authorizeMember(Request $request, int $id): Member
    {
        $member = $request->user();

        if (! $member || (int) $member->id !== (int) $id) {
            abort(403, 'You are not authorized to view this member\'s information.');
        }

        return $member;
    }

    /**
     * GET /api/members/{id}/profile
     *
     * Returns the full profile for the member's personal portal:
     * personal info, ministry, role/notes, newsletter status,
     * plus summarized counts for events & prayer requests.
     */
    public function profile(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $member->load(['newsletterSubscription']);

        return response()->json([
            'success' => true,
            'member' => [
                'id'             => $member->id,
                'full_name'      => $member->full_name,
                'email'          => $member->email,
                'reg_number'     => $member->reg_number,
                'phone'          => $member->phone,
                'course'         => $member->course,
                'year_of_study'  => $member->year_of_study,
                'ministry'       => $member->ministry,
                'status'         => $member->status,
                'role'           => $member->role,
                'admin_notes'    => $member->admin_notes,
                'joined_at'      => $member->joined_at,
            ],
            'newsletter' => [
                'is_subscribed' => $member->newsletterSubscription->is_subscribed ?? false,
                'subscribed_at' => $member->newsletterSubscription->subscribed_at ?? null,
            ],
            'summary' => [
                'events_count'  => $member->eventRegistrations()->count(),
                'prayers_count' => $member->prayerRequests()->count(),
            ],
        ]);
    }

    /**
     * PUT /api/members/{id}/profile
     *
     * Allows a member to update their own editable details
     * (admin-only fields like role, status, admin_notes are excluded).
     */
    public function updateProfile(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $validated = $request->validate([
            'full_name'     => ['sometimes', 'string', 'max:255'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'course'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'year_of_study' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ministry'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'password'      => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $member->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'member'  => $member->fresh(),
        ]);
    }

    /**
     * GET /api/members/{id}/events
     *
     * Returns the member's event registration history.
     */
    public function events(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $events = $member->eventRegistrations()
            ->orderByDesc('registered_at')
            ->get([
                'id', 'event_id', 'event_name', 'status', 'registered_at',
            ]);

        return response()->json([
            'success' => true,
            'count'   => $events->count(),
            'events'  => $events,
        ]);
    }

    /**
     * GET /api/members/{id}/prayers
     *
     * Returns the member's submitted prayer requests.
     */
    public function prayers(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $prayers = $member->prayerRequests()
            ->orderByDesc('submitted_at')
            ->get([
                'id', 'category', 'request', 'is_anonymous', 'status', 'submitted_at',
            ]);

        return response()->json([
            'success' => true,
            'count'   => $prayers->count(),
            'prayers' => $prayers,
        ]);
    }

    /**
     * PUT /api/members/{id}/newsletter
     *
     * Toggle the member's own newsletter subscription.
     */
    public function updateNewsletter(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $validated = $request->validate([
            'is_subscribed' => ['required', 'boolean'],
        ]);

        $sub = $member->newsletterSubscription()->firstOrCreate(
            ['email' => $member->email],
            ['subscribed_at' => now()]
        );

        $sub->is_subscribed = $validated['is_subscribed'];
        if ($validated['is_subscribed'] && ! $sub->subscribed_at) {
            $sub->subscribed_at = now();
        }
        $sub->save();

        return response()->json([
            'success' => true,
            'message' => $validated['is_subscribed']
                ? 'You are now subscribed to the newsletter.'
                : 'You have unsubscribed from the newsletter.',
            'is_subscribed' => $sub->is_subscribed,
        ]);
    }
}
