<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\NewsletterSubscriber;
use App\Models\PrayerRequest;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * GET /api/admin/stats
     */
    public function stats()
    {
        return response()->json([
            'members'                 => Member::where('status', 'active')->count(),
            'prayers'                 => PrayerRequest::count(),
            'prayers_pending'         => PrayerRequest::where('status', 'pending')->count(),
            'event_registrations'     => EventRegistration::count(),
            'newsletter_subscribers'  => NewsletterSubscriber::where('is_subscribed', true)->count(),
            'unread_messages'         => ContactMessage::where('read_status', false)->count(),
        ]);
    }

    /**
     * GET /api/admin/members
     * Returns ALL members with full profile info, including their
     * event registrations and prayer requests (admin can view everything).
     */
    public function members()
    {
        $members = Member::with(['eventRegistrations', 'prayerRequests', 'newsletterSubscription'])
            ->orderByDesc('joined_at')
            ->get();

        return response()->json($members);
    }

    /**
     * GET /api/admin/members/{id}
     * Full single-member profile for the admin's detail view.
     */
    public function memberShow(int $id)
    {
        $member = Member::with(['eventRegistrations', 'prayerRequests', 'newsletterSubscription'])
            ->findOrFail($id);

        return response()->json($member);
    }

    /**
     * PUT /api/admin/members/{id}
     * Admin can update a member's information, role, status, and notes.
     */
    public function memberUpdate(Request $request, int $id)
    {
        $member = Member::findOrFail($id);

        $validated = $request->validate([
            'full_name'     => ['sometimes', 'string', 'max:255'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'course'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'year_of_study' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ministry'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'        => ['sometimes', 'in:active,inactive'],
            'role'          => ['sometimes', 'string', 'max:50'],
            'admin_notes'   => ['sometimes', 'nullable', 'string'],
        ]);

        $member->update($validated);

        return response()->json(['success' => true, 'member' => $member->fresh()]);
    }

    /**
     * PUT /api/admin/members/{id}/status
     * Quick toggle for active/inactive used by the dashboard table.
     */
    public function memberStatus(Request $request, int $id)
    {
        $request->validate(['status' => ['required', 'in:active,inactive']]);

        $member = Member::findOrFail($id);
        $member->update(['status' => $request->status]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/admin/members/{id}
     */
    public function memberDelete(int $id)
    {
        Member::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/admin/prayers
     */
    public function prayers()
    {
        $prayers = PrayerRequest::with('member:id,full_name,email')
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json($prayers);
    }

    /**
     * PUT /api/admin/prayers/{id}/status
     */
    public function prayerStatus(Request $request, int $id)
    {
        $request->validate(['status' => ['required', 'in:pending,prayed']]);

        $prayer = PrayerRequest::findOrFail($id);
        $prayer->update(['status' => $request->status]);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/admin/registrations
     */
    public function registrations()
    {
        $registrations = EventRegistration::with('member:id,full_name,email')
            ->orderByDesc('registered_at')
            ->get();

        return response()->json($registrations);
    }

    /**
     * PUT /api/admin/registrations/{id}/status
     */
    public function registrationStatus(Request $request, int $id)
    {
        $request->validate(['status' => ['required', 'in:registered,attended,cancelled']]);

        $registration = EventRegistration::findOrFail($id);
        $registration->update(['status' => $request->status]);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/admin/messages
     */
    public function messages()
    {
        return response()->json(
            ContactMessage::orderByDesc('sent_at')->get()
        );
    }

    /**
     * PUT /api/admin/messages/{id}/read
     */
    public function messageRead(int $id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['read_status' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/admin/subscribers
     */
    public function subscribers()
    {
        return response()->json(
            NewsletterSubscriber::orderByDesc('subscribed_at')->get()
        );
    }
}
