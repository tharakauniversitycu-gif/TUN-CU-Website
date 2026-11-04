<?php

namespace Database\Seeders;

use App\Models\Docket;
use App\Models\Ministry;
use Illuminate\Database\Seeder;

class DocketAndMinistrySeeder extends Seeder
{
    public function run(): void
    {
        $dockets = [
            [
                'name'        => 'Music Docket',
                'code'        => 'music',
                'ec_position' => 'Music Coordinator',
                'icon'        => '🎵',
                'description' => 'Oversees Praise & Worship, Choir, and Instrumentalists ministries to lead the union in worship and musical excellence.',
                'sort_order'  => 1,
                'ministries'  => [
                    [
                        'name'             => 'Praise & Worship Ministry',
                        'code'             => 'praise-worship',
                        'description'      => 'Leads praise and worship ministrations during CU fellowship services and events.',
                        'registration_fee' => 50,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'Choir Ministry',
                        'code'             => 'choir',
                        'description'      => 'Vocal choral ministration, presentations, and specialized worship arrangements.',
                        'registration_fee' => 50,
                        'sort_order'       => 2,
                    ],
                    [
                        'name'             => 'Instrumentalists Ministry',
                        'code'             => 'instrumentalists',
                        'description'      => 'Musical instruments, setups, sound rehearsals, technical accompaniment, and musical training.',
                        'registration_fee' => 0,
                        'sort_order'       => 3,
                    ],
                ],
            ],
            [
                'name'        => 'Missions & Evangelism Docket',
                'code'        => 'missions-evangelism',
                'ec_position' => 'Missions and Evangelism Coordinator',
                'icon'        => '🌍',
                'description' => 'Mandated to lead and coordinate all evangelistic, discipleship, and outreach activities in and outside Tharaka University.',
                'sort_order'  => 2,
                'ministries'  => [
                    [
                        'name'             => 'In-Reach Ministry',
                        'code'             => 'in-reach',
                        'description'      => 'Campus fellowship evangelism, door-to-door hostel outreach, and spiritual follow-up within campus.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'Outreach Ministry',
                        'code'             => 'outreach',
                        'description'      => 'Leads missions, crusades, and evangelistic trips beyond campus into local communities and partner regions.',
                        'registration_fee' => 0,
                        'sort_order'       => 2,
                    ],
                    [
                        'name'             => 'High School Ministry',
                        'code'             => 'high-school',
                        'description'      => 'Evangelizing and discipling high school and Sunday school students through school visits and weekend rallies.',
                        'registration_fee' => 0,
                        'sort_order'       => 3,
                    ],
                ],
            ],
            [
                'name'        => 'ICT Docket',
                'code'        => 'ict',
                'ec_position' => 'ICT Coordinator',
                'icon'        => '💻',
                'description' => 'Serves as the digital and media arm of the union, supporting operations through technology, publicity, documentation, and innovation.',
                'sort_order'  => 3,
                'ministries'  => [
                    [
                        'name'             => 'IT Ministry',
                        'code'             => 'it-ministry',
                        'description'      => 'Projection during services, livestreaming, website & database maintenance, and digital technical support.',
                        'registration_fee' => 50,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'Publicity Ministry',
                        'code'             => 'publicity',
                        'description'      => 'Digital media design, posters, event branding, social media updates, and announcements.',
                        'registration_fee' => 50,
                        'sort_order'       => 2,
                    ],
                    [
                        'name'             => 'Editorial Ministry',
                        'code'             => 'editorial',
                        'description'      => 'Articles, devotionals, voice-overs, magazine publications, newsletters, and library administration.',
                        'registration_fee' => 50,
                        'sort_order'       => 3,
                    ],
                ],
            ],
            [
                'name'        => 'Discipleship Docket',
                'code'        => 'discipleship',
                'ec_position' => 'Discipleship Coordinator',
                'icon'        => '🌱',
                'description' => 'Tasked with nurturing believers, grounding new converts in the Word, and developing mature disciples of Christ.',
                'sort_order'  => 4,
                'ministries'  => [
                    [
                        'name'             => 'BEST-P (Bible Exposition & Self-Training)',
                        'code'             => 'best-p',
                        'description'      => 'Equips members in sound biblical interpretation, hermeneutics, and apologetics in partnership with FOCUS.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'Nurture Class & Faith Foundation',
                        'code'             => 'nurture-class',
                        'description'      => 'Strengthens the spiritual foundation of new believers in salvation, prayer, and basic Christian doctrines.',
                        'registration_fee' => 0,
                        'sort_order'       => 2,
                    ],
                    [
                        'name'             => 'Follow-Up Ministry',
                        'code'             => 'follow-up',
                        'description'      => 'Dedicated follow-up team supporting new converts immediately after decisions are made.',
                        'registration_fee' => 0,
                        'sort_order'       => 3,
                    ],
                    [
                        'name'             => 'Vuka-FYT & Transition Program',
                        'code'             => 'vuka-fyt',
                        'description'      => 'Spiritual transition and equipping program preparing students for deeper kingdom service.',
                        'registration_fee' => 0,
                        'sort_order'       => 4,
                    ],
                ],
            ],
            [
                'name'        => 'Bible Study Docket',
                'code'        => 'bible-study',
                'ec_position' => 'Bible Study Coordinator',
                'icon'        => '📖',
                'description' => 'Responsible for weekly small-group Bible studies, leader trainings, study guides, and spiritual examinations.',
                'sort_order'  => 5,
                'ministries'  => [
                    [
                        'name'             => 'Bible Study Groups & Pastors',
                        'code'             => 'bible-study-groups',
                        'description'      => 'Small interactive discipleship circles (5-8 members) across year groups studying scripture together.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'Consistency Bible Reading (CBR)',
                        'code'             => 'cbr',
                        'description'      => 'Encourages systematic, daily engagement with the Bible and accountability pairs.',
                        'registration_fee' => 0,
                        'sort_order'       => 2,
                    ],
                ],
            ],
            [
                'name'        => 'Welfare Docket',
                'code'        => 'welfare',
                'ec_position' => 'Welfare Coordinator',
                'icon'        => '🤝',
                'description' => 'Promotes unity, compassion, and mutual care among members through practical service, the CU Canteen, and Helping Kitty.',
                'sort_order'  => 6,
                'ministries'  => [
                    [
                        'name'             => 'Welfare Ministry & Helping Kitty',
                        'code'             => 'welfare-kitty',
                        'description'      => 'Provides support to members facing temporary hardships, bereavement, illness, and emergency assistance.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'CU Canteen Project',
                        'code'             => 'cu-canteen',
                        'description'      => 'Affordable quality meals in a Christ-centered environment, teaching stewardship and servant leadership.',
                        'registration_fee' => 0,
                        'sort_order'       => 2,
                    ],
                ],
            ],
            [
                'name'        => 'Intercessory Docket',
                'code'        => 'intercessory',
                'ec_position' => 'Prayer Coordinator',
                'icon'        => '🙏',
                'description' => 'Chairs prayer sessions, prayer vigils, fasting retreats, and intercedes for the Union, University, and nation.',
                'sort_order'  => 7,
                'ministries'  => [
                    [
                        'name'             => 'Intercessory & Prayer Ministry',
                        'code'             => 'intercessory-prayer',
                        'description'      => 'Daily prayer meetings, night vigils, prayer chains, and emergency intercession.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                ],
            ],
            [
                'name'        => 'Organising Secretary Docket',
                'code'        => 'organising-sec',
                'ec_position' => 'Organising Secretary',
                'icon'        => '🎭',
                'description' => 'Coordinates venue and facility bookings, special committees, theatre/creative arts, and brothers ministry.',
                'sort_order'  => 8,
                'ministries'  => [
                    [
                        'name'             => 'Theatre & Creative Ministry ("House of Grace")',
                        'code'             => 'theatre-creative',
                        'description'      => 'Skits, drama, spoken word, dance, and creative productions that communicate biblical truth.',
                        'registration_fee' => 100,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'Brothers\' Ministry',
                        'code'             => 'brothers-ministry',
                        'description'      => 'Mentoring young men to live godly, responsible, pure lives of Christian leadership.',
                        'registration_fee' => 0,
                        'sort_order'       => 2,
                    ],
                ],
            ],
            [
                'name'        => 'Vice Chair Docket',
                'code'        => 'vice-chair',
                'ec_position' => 'Vice Chairperson',
                'icon'        => '🌸',
                'description' => 'Coordinates the spiritual mentorship and discipleship of ladies in the Union through Sisters\' ministry.',
                'sort_order'  => 9,
                'ministries'  => [
                    [
                        'name'             => 'Sisters\' Ministry',
                        'code'             => 'sisters-ministry',
                        'description'      => 'Nurturing Christian young women in spiritual maturity, purity, character, and sisterly fellowship.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                ],
            ],
            [
                'name'        => 'Vice Secretary Docket',
                'code'        => 'vice-secretary',
                'ec_position' => 'Vice Secretary',
                'icon'        => '✨',
                'description' => 'Coordinates hospitality, order, and logistical service during union gatherings.',
                'sort_order'  => 10,
                'ministries'  => [
                    [
                        'name'             => 'Ushering Ministry',
                        'code'             => 'ushering',
                        'description'      => 'Welcoming members and guests, venue seating, orderliness, and offering collections.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                    [
                        'name'             => 'Catering Ministry',
                        'code'             => 'catering',
                        'description'      => 'Meal coordination, hospitality, refreshments during retreats, conferences, and fellowships.',
                        'registration_fee' => 0,
                        'sort_order'       => 2,
                    ],
                ],
            ],
            [
                'name'        => 'Finance Docket',
                'code'        => 'finance',
                'ec_position' => 'Treasurer',
                'icon'        => '💰',
                'description' => 'Maintains stewardship, budget oversight, financial transactions, and asset accountability.',
                'sort_order'  => 11,
                'ministries'  => [
                    [
                        'name'             => 'Treasury & Finance Subcommittee',
                        'code'             => 'treasury-finance',
                        'description'      => 'Financial management, books of accounts, audits, and asset inventory.',
                        'registration_fee' => 0,
                        'sort_order'       => 1,
                    ],
                ],
            ],
        ];

        foreach ($dockets as $docketData) {
            $ministries = $docketData['ministries'];
            unset($docketData['ministries']);

            $docket = Docket::updateOrCreate(
                ['code' => $docketData['code']],
                $docketData
            );

            foreach ($ministries as $ministryData) {
                Ministry::updateOrCreate(
                    ['code' => $ministryData['code']],
                    array_merge($ministryData, ['docket_id' => $docket->id])
                );
            }
        }
    }
}
