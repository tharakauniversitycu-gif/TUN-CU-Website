<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('reg_number')->unique()->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('course')->nullable();
            $table->unsignedTinyInteger('course_duration')->default(4); // 4 or 5 years
            $table->unsignedTinyInteger('year_of_study')->default(1);   // 1 to 5; 0 = Associate
            $table->enum('membership_category', ['full', 'special', 'associate'])->default('full');
            $table->enum('role', ['member', 'subcommittee', 'executive', 'associate', 'patron'])->default('member');
            $table->string('ec_position')->nullable(); // e.g., Chairperson, Secretary, Music Coordinator
            $table->enum('status', ['active', 'inactive', 'associate', 'suspended'])->default('active');
            $table->boolean('is_year_confirmed')->default(true); // reset to false on academic year rollover
            $table->timestamp('year_confirmed_at')->nullable();
            $table->timestamp('became_associate_at')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
