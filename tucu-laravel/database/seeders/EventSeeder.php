<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Seed the upcoming events shown on the homepage.
     */
    public function run(): void
    {
        $events = [
            [
                'title'       => 'Friday Evening Fellowship',
                'category'    => 'Weekly Fellowship',
                'description' => 'Our weekly gathering for worship, Word, and community. All students warmly welcome to attend and participate.',
                'location'    => 'Main Chapel, Tharaka University',
                'starts_at'   => now()->next('Friday')->setTime(16, 0),
                'ends_at'     => now()->next('Friday')->setTime(18, 30),
            ],
            [
                'title'       => 'End of Semester Night of Prayer',
                'category'    => 'Prayer Vigil',
                'description' => 'Come before the Lord as exams approach. A powerful night of intercession, worship, and declaration over the campus.',
                'location'    => 'CU Prayer Room, Block C',
                'starts_at'   => now()->next('Friday')->addWeek()->setTime(22, 0),
                'ends_at'     => now()->next('Saturday')->addWeek()->setTime(4, 0),
            ],
            [
                'title'       => 'Campus Missions & Evangelism Day',
                'category'    => 'Conference',
                'description' => 'A full day of outreach, training, and street evangelism. Learn how to share your faith boldly and effectively.',
                'location'    => 'University Grounds & Surrounding Area',
                'starts_at'   => now()->next('Saturday')->addWeeks(2)->setTime(8, 0),
                'ends_at'     => now()->next('Saturday')->addWeeks(2)->setTime(17, 0),
            ],
        ];

        foreach ($events as $event) {
            Event::firstOrCreate(['title' => $event['title']], $event);
        }
    }
}
