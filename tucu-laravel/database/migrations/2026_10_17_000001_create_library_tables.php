<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->string('name'); $t->string('slug')->unique(); $t->timestamps();
        });
        Schema::create('library_books', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->string('slug')->unique(); $t->text('title'); $t->text('author')->nullable();
            $t->text('description')->nullable();
            $t->foreignId('category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $t->string('status')->default('draft')->index();
            $t->unsignedBigInteger('current_file_id')->nullable();
            $t->string('cover_path')->nullable(); $t->boolean('is_featured')->default(false);
            $t->timestamp('published_at')->nullable(); $t->timestamp('legacy_uploaded_at')->nullable();
            $t->timestamps();
        });
        Schema::create('library_book_files', function (Blueprint $t) {
            $t->id(); $t->foreignId('book_id')->constrained('library_books')->restrictOnDelete();
            $t->unsignedInteger('version'); $t->string('path')->unique();
            $t->text('original_filename'); $t->string('mime')->default('application/pdf');
            $t->unsignedBigInteger('byte_size'); $t->char('sha256', 64);
            $t->unsignedInteger('page_count')->nullable(); $t->boolean('encrypted')->default(false);
            $t->string('validation_status')->default('pending'); $t->timestamps();
            $t->unique(['book_id', 'version']);
        });
        Schema::table('library_books', fn (Blueprint $t) => $t->foreign('current_file_id')->references('id')->on('library_book_files')->restrictOnDelete());
        Schema::create('library_reading_progress', function (Blueprint $t) {
            $t->id(); $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->foreignId('book_id')->constrained('library_books')->restrictOnDelete();
            $t->foreignId('file_id')->constrained('library_book_files')->restrictOnDelete();
            $t->unsignedInteger('current_page'); $t->unsignedInteger('furthest_page');
            $t->decimal('page_offset', 5, 4)->default(0); $t->string('status')->default('in_progress');
            $t->unsignedInteger('revision')->default(1); $t->timestamp('started_at');
            $t->timestamp('last_read_at'); $t->timestamp('completed_at')->nullable();
            $t->unique(['member_id', 'book_id', 'file_id'], 'library_progress_owner_version');
            $t->index(['member_id', 'status', 'last_read_at'], 'library_progress_shelf');
        });
        Schema::create('library_likes', function (Blueprint $t) {
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->foreignId('book_id')->constrained('library_books')->restrictOnDelete();
            $t->timestamp('created_at'); $t->primary(['member_id', 'book_id']);
        });
        Schema::create('library_downloads', function (Blueprint $t) {
            $t->id(); $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->foreignId('book_id')->constrained('library_books')->restrictOnDelete();
            $t->foreignId('file_id')->constrained('library_book_files')->restrictOnDelete();
            $t->uuid('request_key'); $t->timestamp('requested_at');
            $t->unique(['member_id', 'request_key']);
        });
        Schema::create('library_import_runs', function (Blueprint $t) {
            $t->id(); $t->string('source_label'); $t->char('manifest_hash', 64)->unique();
            $t->string('status'); $t->json('summary')->nullable(); $t->timestamps();
        });
        Schema::create('library_import_records', function (Blueprint $t) {
            $t->id(); $t->foreignId('run_id')->constrained('library_import_runs');
            $t->string('entity_type'); $t->string('source_key'); $t->char('source_hash', 64);
            $t->string('destination'); $t->unique(['run_id', 'entity_type', 'source_key'], 'library_import_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_import_records'); Schema::dropIfExists('library_import_runs');
        Schema::dropIfExists('library_downloads'); Schema::dropIfExists('library_likes');
        Schema::dropIfExists('library_reading_progress');
        Schema::table('library_books', fn (Blueprint $t) => $t->dropForeign(['current_file_id']));
        Schema::dropIfExists('library_book_files'); Schema::dropIfExists('library_books');
        Schema::dropIfExists('library_categories');
    }
};
