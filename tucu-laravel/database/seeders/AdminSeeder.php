<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default super admin account.
     *
     * Run with: php artisan db:seed --class=AdminSeeder
     * (or include it in DatabaseSeeder::run())
     */
    public function run(): void
    {
        Admin::firstOrCreate(
            ['username' => 'admin'],
            [
                'email'    => 'admin@tucu.ac.ke',
                'password' => Hash::make('tucu2026admin'),
                'role'     => 'superadmin',
            ]
        );

        $this->command->info('✅ Default admin created → username: admin | password: tucu2026admin');
        $this->command->warn('⚠️  Change this password immediately after first login.');
    }
}
