<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_requests', function (Blueprint $table) {
            $table->id();
            // Nullable so non-registered visitors can also submit prayer requests
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('course')->nullable();
            $table->string('category');
            $table->text('request');
            $table->boolean('is_anonymous')->default(false);
            $table->enum('status', ['pending', 'prayed'])->default('pending');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_requests');
    }
};
