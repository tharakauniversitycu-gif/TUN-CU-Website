<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();       // e.g. "2024/2025"
            $table->date('start_date')->nullable();   // e.g. September
            $table->date('end_date')->nullable();     // e.g. April/May
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('spiritual_years', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();       // e.g. "2024/2025"
            $table->string('theme')->nullable();      // e.g. "Come now, let us reason together — Isa 1:18"
            $table->date('start_date')->nullable();   // e.g. November 1
            $table->date('end_date')->nullable();     // e.g. October 31
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('member_year_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->unsignedTinyInteger('year_of_study'); // 1 to 5; 0 = Associate
            $table->string('action')->default('registered'); // registered, auto_promoted, confirmed, repeated, graduated
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_year_history');
        Schema::dropIfExists('spiritual_years');
        Schema::dropIfExists('academic_years');
    }
};
