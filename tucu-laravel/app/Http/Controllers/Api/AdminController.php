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
        $members = Member::with([
            'ministries.docket',
            'eventRegistrations',
            'prayerRequests',
            'newsletterSubscription'
        ])
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
        $member = Member::with([
            'ministries.docket',
            'yearHistory.academicYear',
            'eventRegistrations',
            'prayerRequests',
            'newsletterSubscription'
        ])->findOrFail($id);

        return response()->json($member);
    }

    /**
     * PUT /api/admin/members/{id}
     * Admin can update a member's information, role, status, ministries, and notes.
     */
    public function memberUpdate(Request $request, int $id)
    {
        $member = Member::findOrFail($id);

        $validated = $request->validate([
            'full_name'           => ['sometimes', 'string', 'max:255'],
            'phone'               => ['sometimes', 'nullable', 'string', 'max:20'],
            'course'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'course_duration'     => ['sometimes', 'integer', 'in:4,5'],
            'year_of_study'       => ['sometimes', 'integer', 'between:0,5'],
            'membership_category' => ['sometimes', 'in:full,special,associate'],
            'status'              => ['sometimes', 'in:active,inactive,associate,suspended'],
            'role'                => ['sometimes', 'in:member,subcommittee,executive,associate,patron'],
            'ec_position'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'admin_notes'         => ['sometimes', 'nullable', 'string'],
            'ministry_ids'        => ['sometimes', 'nullable', 'array', 'max:3'],
            'ministry_ids.*'      => ['integer', 'exists:ministries,id'],
        ]);

        if (array_key_exists('ministry_ids', $validated)) {
            $member->ministries()->sync($validated['ministry_ids'] ?? []);
            unset($validated['ministry_ids']);
        }

        if (isset($validated['year_of_study']) && (int) $validated['year_of_study'] === 0) {
            $validated['role'] = 'associate';
            $validated['membership_category'] = 'associate';
            $validated['status'] = 'associate';
            if (! $member->became_associate_at) {
                $validated['became_associate_at'] = now();
            }
        }

        $member->update($validated);

        return response()->json(['success' => true, 'member' => $member->fresh(['ministries.docket'])]);
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

    /**
     * POST /api/admin/academic-years/rollover
     *
     * Promotes all active students to the next academic year (auto-bump),
     * triggers year confirmation required flag, and graduates finalists
     * to Associate status.
     */
    public function academicYearRollover(Request $request)
    {
        $validated = $request->validate([
            'new_label' => ['required', 'string', 'max:50'], // e.g. "2026/2027"
        ]);

        // 1. Mark previous academic years as non-current and create/set new one
        \App\Models\AcademicYear::where('is_current', true)->update(['is_current' => false]);
        $academicYear = \App\Models\AcademicYear::updateOrCreate(
            ['label' => $validated['new_label']],
            ['is_current' => true]
        );

        $bumpedCount = 0;
        $graduatedCount = 0;

        // 2. Fetch all active undergraduate members who are not yet Associates
        $members = Member::where('status', 'active')
            ->where('role', '!=', 'associate')
            ->where('membership_category', '!=', 'associate')
            ->where('year_of_study', '>', 0)
            ->get();

        foreach ($members as $member) {
            $nextYear = (int) $member->year_of_study + 1;
            $courseDuration = (int) ($member->course_duration ?: 4);

            if ($nextYear > $courseDuration) {
                // Completed course duration -> Graduate to Associate
                $member->update([
                    'year_of_study'       => 0,
                    'membership_category' => 'associate',
                    'role'                => 'associate',
                    'status'              => 'associate',
                    'is_year_confirmed'   => false, // prompts verification on login
                    'became_associate_at' => now(),
                ]);

                \App\Models\MemberYearHistory::create([
                    'member_id'        => $member->id,
                    'academic_year_id' => $academicYear->id,
                    'year_of_study'    => 0,
                    'action'           => 'auto_graduated_associate',
                    'notes'            => "Completed {$courseDuration}-year course upon {$academicYear->label} rollover",
                    'confirmed_at'     => now(),
                ]);

                $graduatedCount++;
            } else {
                // Advance to next study year
                $member->update([
                    'year_of_study'     => $nextYear,
                    'is_year_confirmed' => false, // prompts confirmation modal upon login
                ]);

                \App\Models\MemberYearHistory::create([
                    'member_id'        => $member->id,
                    'academic_year_id' => $academicYear->id,
                    'year_of_study'    => $nextYear,
                    'action'           => 'auto_promoted',
                    'notes'            => "Promoted to Year {$nextYear} upon {$academicYear->label} rollover",
                    'confirmed_at'     => now(),
                ]);

                $bumpedCount++;
            }
        }

        return response()->json([
            'success'         => true,
            'message'         => "Academic year {$academicYear->label} successfully activated.",
            'academic_year'   => $academicYear,
            'bumped_students' => $bumpedCount,
            'new_associates'  => $graduatedCount,
        ]);
    }
}
