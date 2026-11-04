<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Docket;
use App\Models\Ministry;
use App\Models\SpiritualYear;
use Illuminate\Database\Seeder;

class YearCalendarSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Academic Years
        AcademicYear::updateOrCreate(
            ['label' => '2023/2024'],
            [
                'start_date' => '2023-09-01',
                'end_date'   => '2024-04-30',
                'is_current' => false,
            ]
        );

        AcademicYear::updateOrCreate(
            ['label' => '2024/2025'],
            [
                'start_date' => '2024-09-01',
                'end_date'   => '2025-04-30',
                'is_current' => false,
            ]
        );

        AcademicYear::updateOrCreate(
            ['label' => '2025/2026'],
            [
                'start_date' => '2025-09-01',
                'end_date'   => '2026-04-30',
                'is_current' => true, // currently active academic cycle
            ]
        );

        // 2. Spiritual Years (Nov to Nov)
        SpiritualYear::updateOrCreate(
            ['label' => '2024/2025'],
            [
                'theme'      => 'Come now, let us reason together — Isaiah 1:18',
                'start_date' => '2024-11-01',
                'end_date'   => '2025-10-31',
                'is_current' => false,
            ]
        );

        SpiritualYear::updateOrCreate(
            ['label' => '2025/2026'],
            [
                'theme'      => 'Rooted and Built Up in Him — Colossians 2:6-7',
                'start_date' => '2025-11-01',
                'end_date'   => '2026-10-31',
                'is_current' => true,
            ]
        );

        // 3. Sample Targeted Announcements
        $musicDocket = Docket::where('code', 'music')->first();
        $praiseMinistry = Ministry::where('code', 'praise-worship')->first();

        Announcement::firstOrCreate(
            ['title' => 'Welcome to Spiritual Year 2025/2026 — Theme Unveiled!'],
            [
                'content'           => "Grace and peace, saints! We enter this spiritual season with great anticipation as we walk under our theme: 'Rooted and Built Up in Him' (Colossians 2:6-7). Let us commit every fellowship, study circle, and outreach into the Lord's hands.",
                'target_type'       => 'all',
                'author_name'       => 'Executive Committee',
                'author_role_label' => 'Executive Committee',
                'is_pinned'         => true,
                'published_at'      => now(),
            ]
        );

        if ($musicDocket) {
            Announcement::firstOrCreate(
                ['title' => 'Music Docket: Sound Checks & Wednesday Practice'],
                [
                    'content'           => 'All praise team ministers, choir members, and instrumentalists are reminded that Wednesday practice begins promptly at 7:15 PM in the main sanctuary. Punctuality is required as per docket policy.',
                    'target_type'       => 'docket',
                    'docket_id'         => $musicDocket->id,
                    'author_name'       => 'Music Coordinator',
                    'author_role_label' => 'Music Coordinator (EC)',
                    'is_pinned'         => false,
                    'published_at'      => now()->subDays(2),
                ]
            );
        }

        if ($praiseMinistry) {
            Announcement::firstOrCreate(
                ['title' => 'Praise & Worship: Uniform Distribution & Auditions'],
                [
                    'content'           => 'New members wishing to serve on the vocal team are invited for vocal placement tests this Saturday from 2:00 PM. Registered members should also finalize uniform procurement with the secretary.',
                    'target_type'       => 'ministry',
                    'ministry_id'       => $praiseMinistry->id,
                    'author_name'       => 'Praise & Worship Chairperson',
                    'author_role_label' => 'Subcommittee Leader',
                    'is_pinned'         => false,
                    'published_at'      => now()->subDay(),
                ]
            );
        }

        Announcement::firstOrCreate(
            ['title' => 'Associates Fellowship: Annual Mentorship Weekend'],
            [
                'content'           => 'Warm greetings to all TUCU Associates! We invite you to the upcoming Campus Mentorship Summit where associates will partner with current 3rd and 4th-year students for professional and spiritual guidance.',
                'target_type'       => 'associates',
                'author_name'       => 'Partnership & Associates Coordinator',
                'author_role_label' => 'Executive Office',
                'is_pinned'         => true,
                'published_at'      => now(),
            ]
        );
    }
}
