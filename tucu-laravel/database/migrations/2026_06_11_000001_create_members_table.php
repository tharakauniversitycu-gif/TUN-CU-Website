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
            $table->string('course')->nullable();           // department/college
            $table->string('year_of_study')->nullable();
            $table->string('ministry')->default('General');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('admin_notes')->nullable();         // admin-assigned notes
            $table->string('role')->default('member');       // member / leader / etc
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
