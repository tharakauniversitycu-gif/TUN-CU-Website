<?php

namespace Tests\Feature;

use App\Models\LibraryBook;
use App\Models\LibraryBookFile;
use App\Models\LibraryCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LibraryDirectoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepared_directory_import_creates_drafts_and_skips_an_identical_manifest(): void
    {
        $workspace = storage_path('framework/testing/library-directory-import-'.uniqid());
        $source = $workspace.DIRECTORY_SEPARATOR.'source';
        $covers = $workspace.DIRECTORY_SEPARATOR.'covers';
        File::ensureDirectoryExists($source);
        File::ensureDirectoryExists($covers);
        config(['library.root' => $workspace.DIRECTORY_SEPARATOR.'private']);

        $pdfPath = $source.DIRECTORY_SEPARATOR.'test-book.pdf';
        file_put_contents($pdfPath, "%PDF-1.7\nTest fixture\n%%EOF");
        $coverPath = $covers.DIRECTORY_SEPARATOR.'test-cover.jpg';
        $image = imagecreatetruecolor(600, 800);
        imagejpeg($image, $coverPath);
        imagedestroy($image);

        $manifest = [
            'format' => 1,
            'source_roots' => [$source],
            'cover_root' => $covers,
            'books' => [[
                'source_path' => $pdfPath,
                'filename' => 'test-book.pdf',
                'byte_size' => filesize($pdfPath),
                'sha256' => hash_file('sha256', $pdfPath),
                'title' => 'A Test Book',
                'author' => 'Test Author',
                'description' => 'A test book for the library.',
                'category' => 'Prayer & Spiritual Growth',
                'page_count' => 3,
                'encrypted' => false,
                'validation_status' => 'valid',
                'cover_path' => $coverPath,
            ]],
        ];
        $manifestPath = $workspace.DIRECTORY_SEPARATOR.'manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

        try {
            $this->assertSame(0, Artisan::call('library:import-directory', [
                'manifest' => $manifestPath,
                '--apply' => true,
            ]));
            $book = LibraryBook::with('currentFile')->firstOrFail();
            $this->assertSame('A Test Book', $book->title);
            $this->assertSame('draft', $book->status);
            $this->assertSame('Prayer & Spiritual Growth', LibraryCategory::findOrFail($book->category_id)->name);
            $this->assertSame('valid', $book->currentFile->validation_status);
            $this->assertFileExists(config('library.root').DIRECTORY_SEPARATOR.$book->currentFile->path);
            $this->assertFileExists(config('library.root').DIRECTORY_SEPARATOR.$book->cover_path);

            $book->update(['cover_path' => null]);
            $manifest['books'][0]['description'] = 'Updated metadata for the same PDF.';
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
            Artisan::call('library:import-directory', [
                'manifest' => $manifestPath,
                '--apply' => true,
            ]);
            $this->assertSame(1, LibraryBook::count());
            $this->assertSame(1, LibraryBookFile::count());
            $this->assertNotNull($book->fresh()->cover_path);
        } finally {
            File::deleteDirectory($workspace);
        }
    }
}
