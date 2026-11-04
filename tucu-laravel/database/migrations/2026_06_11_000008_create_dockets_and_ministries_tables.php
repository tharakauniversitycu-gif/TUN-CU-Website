<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dockets', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // e.g. "Music Docket"
            $table->string('code')->unique();            // e.g. "music"
            $table->string('ec_position')->nullable();   // e.g. "Music Coordinator"
            $table->string('icon')->default('fa-layer-group'); // UI icon
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ministries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docket_id')->constrained('dockets')->onDelete('cascade');
            $table->string('name');                      // e.g. "Praise and Worship"
            $table->string('code')->unique();            // e.g. "praise-and-worship"
            $table->text('description')->nullable();
            $table->unsignedInteger('registration_fee')->default(0); // in KES as per policy
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('member_ministry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('ministry_id')->constrained('ministries')->onDelete('cascade');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['member_id', 'ministry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_ministry');
        Schema::dropIfExists('ministries');
        Schema::dropIfExists('dockets');
    }
};
