<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Docket;
use App\Models\Member;
use App\Models\MemberYearHistory;
use App\Models\Ministry;
use App\Models\SpiritualYear;
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
     * Returns full profile with ministries, parent dockets, calendars,
     * year confirmation status, and summary statistics.
     */
    public function profile(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $member->load([
            'ministries.docket',
            'newsletterSubscription',
            'yearHistory.academicYear',
        ]);

        $currentAcademicYear = AcademicYear::current();
        $currentSpiritualYear = SpiritualYear::current();

        // Extract distinct dockets that member is affiliated with
        $affiliatedDockets = $member->ministries
            ->pluck('docket')
            ->filter()
            ->unique('id')
            ->values();

        return response()->json([
            'success' => true,
            'member'  => [
                'id'                  => $member->id,
                'full_name'           => $member->full_name,
                'email'               => $member->email,
                'reg_number'          => $member->reg_number,
                'phone'               => $member->phone,
                'course'              => $member->course,
                'course_duration'     => $member->course_duration,
                'year_of_study'       => $member->year_of_study,
                'membership_category' => $member->membership_category,
                'status'              => $member->status,
                'role'                => $member->role,
                'ec_position'         => $member->ec_position,
                'is_executive'        => $member->isExecutive(),
                'is_associate'        => $member->isAssociate(),
                'is_year_confirmed'   => (bool) $member->is_year_confirmed,
                'year_confirmed_at'   => $member->year_confirmed_at,
                'became_associate_at' => $member->became_associate_at,
                'admin_notes'         => $member->admin_notes,
                'joined_at'           => $member->joined_at,
                'ministries'          => $member->ministries,
                'dockets'             => $affiliatedDockets,
            ],
            'calendars' => [
                'academic_year'  => $currentAcademicYear?->label,
                'spiritual_year' => $currentSpiritualYear?->label,
                'spiritual_theme' => $currentSpiritualYear?->theme,
            ],
            'newsletter' => [
                'is_subscribed' => $member->newsletterSubscription->is_subscribed ?? false,
                'subscribed_at' => $member->newsletterSubscription->subscribed_at ?? null,
            ],
            'summary' => [
                'events_count'     => $member->eventRegistrations()->count(),
                'prayers_count'    => $member->prayerRequests()->count(),
                'ministries_count' => $member->ministries()->count(),
            ],
        ]);
    }

    /**
     * PUT /api/members/{id}/profile
     *
     * Allows a member to update editable details, including up to 3 ministries.
     */
    public function updateProfile(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $validated = $request->validate([
            'full_name'       => ['sometimes', 'string', 'max:255'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:20'],
            'course'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'course_duration' => ['sometimes', 'nullable', 'integer', 'in:4,5'],
            'password'        => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
            'ministry_ids'    => ['sometimes', 'nullable', 'array', 'max:3'],
            'ministry_ids.*'  => ['integer', 'exists:ministries,id'],
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (array_key_exists('ministry_ids', $validated)) {
            $member->ministries()->sync($validated['ministry_ids'] ?? []);
            unset($validated['ministry_ids']);
        }

        $member->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'member'  => $member->fresh(['ministries.docket']),
        ]);
    }

    /**
     * POST /api/members/{id}/confirm-year
     *
     * Called when member verifies or updates their academic study year
     * or transitions to Associate status.
     */
    public function confirmYear(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $validated = $request->validate([
            'action'        => ['required', 'string', 'in:confirm,update,graduate'],
            'year_of_study' => ['nullable', 'integer', 'between:0,5'],
            'notes'         => ['nullable', 'string', 'max:255'],
        ]);

        $currentAcademicYear = AcademicYear::current();
        $action = $validated['action'];
        $notes = $validated['notes'] ?? null;

        if ($action === 'graduate' || $validated['year_of_study'] === 0) {
            $member->update([
                'year_of_study'       => 0,
                'membership_category' => 'associate',
                'role'                => 'associate',
                'status'              => 'associate',
                'is_year_confirmed'   => true,
                'year_confirmed_at'   => now(),
                'became_associate_at' => $member->became_associate_at ?? now(),
            ]);

            MemberYearHistory::create([
                'member_id'        => $member->id,
                'academic_year_id' => $currentAcademicYear?->id,
                'year_of_study'    => 0,
                'action'           => 'graduated',
                'notes'            => $notes ?? 'Graduated to Associate of the Union',
                'confirmed_at'     => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Congratulations on your graduation! Welcome to the TUCU Associates Fellowship.',
                'member'  => $member->fresh(['ministries.docket']),
            ]);
        }

        $targetYear = $validated['year_of_study'] ?? $member->year_of_study;

        // Check if student reached beyond course duration (e.g. 4 or 5)
        if ($targetYear > $member->course_duration) {
            $member->update([
                'year_of_study'       => 0,
                'membership_category' => 'associate',
                'role'                => 'associate',
                'status'              => 'associate',
                'is_year_confirmed'   => true,
                'year_confirmed_at'   => now(),
                'became_associate_at' => now(),
            ]);

            MemberYearHistory::create([
                'member_id'        => $member->id,
                'academic_year_id' => $currentAcademicYear?->id,
                'year_of_study'    => 0,
                'action'           => 'graduated',
                'notes'            => 'Completed course duration (' . $member->course_duration . ' years)',
                'confirmed_at'     => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Course completed! You are now an honored Associate of TUCU.',
                'member'  => $member->fresh(['ministries.docket']),
            ]);
        }

        $member->update([
            'year_of_study'     => $targetYear,
            'is_year_confirmed' => true,
            'year_confirmed_at' => now(),
        ]);

        MemberYearHistory::create([
            'member_id'        => $member->id,
            'academic_year_id' => $currentAcademicYear?->id,
            'year_of_study'    => $targetYear,
            'action'           => $action === 'confirm' ? 'confirmed' : 'updated',
            'notes'            => $notes ?? 'Academic year verified by member',
            'confirmed_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Academic year status successfully confirmed.',
            'member'  => $member->fresh(['ministries.docket']),
        ]);
    }

    /**
     * GET /api/members/{id}/announcements
     *
     * Returns targeted announcements for the member.
     */
    public function announcements(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $ministryIds = $member->ministries()->pluck('ministries.id')->toArray();
        $docketIds   = $member->ministries()->pluck('docket_id')->unique()->toArray();

        $query = Announcement::with(['docket', 'ministry'])
            ->where(function ($q) use ($member, $ministryIds, $docketIds) {
                // Union-wide
                $q->where('target_type', 'all');

                // Associates
                if ($member->isAssociate()) {
                    $q->orWhere('target_type', 'associates');
                }

                // Member's dockets
                if (! empty($docketIds)) {
                    $q->orWhere(function ($sub) use ($docketIds) {
                        $sub->where('target_type', 'docket')
                            ->whereIn('docket_id', $docketIds);
                    });
                }

                // Member's ministries
                if (! empty($ministryIds)) {
                    $q->orWhere(function ($sub) use ($ministryIds) {
                        $sub->where('target_type', 'ministry')
                            ->whereIn('ministry_id', $ministryIds);
                    });
                }
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        $announcements = $query->get();

        return response()->json([
            'success'       => true,
            'count'         => $announcements->count(),
            'announcements' => $announcements,
        ]);
    }

    /**
     * POST /api/members/{id}/announcements
     *
     * Executive Committee leaders only: Post an announcement to their docket,
     * a ministry under their docket, or union-wide.
     */
    public function storeAnnouncement(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        if (! $member->isExecutive()) {
            abort(403, 'Only Executive Committee leaders can post announcements.');
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'content'     => ['required', 'string'],
            'target_type' => ['required', 'string', 'in:all,docket,ministry,associates'],
            'docket_id'   => ['nullable', 'integer', 'exists:dockets,id'],
            'ministry_id' => ['nullable', 'integer', 'exists:ministries,id'],
            'is_pinned'   => ['nullable', 'boolean'],
        ]);

        $announcement = Announcement::create([
            'title'             => $validated['title'],
            'content'           => $validated['content'],
            'target_type'       => $validated['target_type'],
            'docket_id'         => $validated['docket_id'] ?? null,
            'ministry_id'       => $validated['ministry_id'] ?? null,
            'author_member_id'  => $member->id,
            'author_name'       => $member->full_name,
            'author_role_label' => $member->ec_position ?? 'Executive Committee',
            'is_pinned'         => $validated['is_pinned'] ?? false,
            'published_at'      => now(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Announcement successfully published.',
            'announcement' => $announcement->load(['docket', 'ministry']),
        ], 201);
    }

    /**
     * GET /api/members/{id}/associate-hub
     *
     * Detailed data for Associates: historical journey, alumni giving details, fellowship news.
     */
    public function associateHub(Request $request, int $id)
    {
        $member = $this->authorizeMember($request, $id);

        $journey = MemberYearHistory::with('academicYear')
            ->where('member_id', $member->id)
            ->orderBy('confirmed_at')
            ->get();

        $eventsAttended = $member->eventRegistrations()
            ->orderByDesc('registered_at')
            ->get();

        return response()->json([
            'success' => true,
            'member'  => [
                'id'                  => $member->id,
                'full_name'           => $member->full_name,
                'became_associate_at' => $member->became_associate_at,
                'joined_at'           => $member->joined_at,
                'ministries'          => $member->ministries()->with('docket')->get(),
            ],
            'journey' => $journey,
            'events'  => $eventsAttended,
            'giving'  => [
                'bank_name'      => 'Co-operative Bank of Kenya',
                'account_name'   => 'Tharaka University Christian Union',
                'account_number' => '01129XXXXXXXXX',
                'paybill'        => '400200',
                'initiatives'    => [
                    ['name' => 'CU Canteen Project', 'desc' => 'Empowering practical ministry & campus student service'],
                    ['name' => 'Helping Kitty', 'desc' => 'Emergency food & basic assistance for needy students'],
                    ['name' => 'Annual Mission Fund', 'desc' => 'In-reach and outreach evangelistic missions'],
                ],
            ],
        ]);
    }

    /**
     * GET /api/members/{id}/events
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
