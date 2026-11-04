<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('target_type', ['all', 'docket', 'ministry', 'associates'])->default('all');
            $table->foreignId('docket_id')->nullable()->constrained('dockets')->nullOnDelete();
            $table->foreignId('ministry_id')->nullable()->constrained('ministries')->nullOnDelete();
            $table->foreignId('author_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('author_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_role_label')->nullable(); // e.g. "Music Coordinator", "Executive Committee"
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
