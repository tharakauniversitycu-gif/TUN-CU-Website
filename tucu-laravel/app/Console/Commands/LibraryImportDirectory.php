<?php

namespace App\Console\Commands;

use App\Models\LibraryBook;
use App\Models\LibraryBookFile;
use App\Models\LibraryCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LibraryImportDirectory extends Command
{
    protected $signature = 'library:import-directory {manifest} {--dry-run} {--apply}';
    protected $description = 'Import prepared local PDF books into the private library as drafts';

    public function handle(): int
    {
        if ((bool) $this->option('apply') === (bool) $this->option('dry-run')) {
            $this->error('Choose exactly one of --dry-run or --apply.');
            return self::FAILURE;
        }

        $manifestPath = realpath($this->argument('manifest'));
        if (! $manifestPath || ! is_file($manifestPath) || ! is_readable($manifestPath)) {
            $this->error('The prepared manifest is missing or unreadable.');
            return self::FAILURE;
        }

        $raw = file_get_contents($manifestPath);
        $manifest = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1
            || ! is_array($manifest['source_roots'] ?? null)
            || ! is_array($manifest['books'] ?? null)) {
            $this->error('The prepared manifest has an unsupported format.');
            return self::FAILURE;
        }

        $roots = array_values(array_filter(array_map(fn ($root) => realpath($root), $manifest['source_roots'])));
        $coverRoot = realpath($manifest['cover_root'] ?? '');
        if (! $roots || ! $coverRoot) {
            $this->error('A source directory or prepared cover directory is unavailable.');
            return self::FAILURE;
        }

        $books = [];
        $hashes = [];
        $categories = [];
        foreach ($manifest['books'] as $record) {
            if (! is_array($record)
                || ! preg_match('/^[a-f0-9]{64}$/', (string) ($record['sha256'] ?? ''))) {
                throw new RuntimeException('A manifest record has invalid metadata or checksum.');
            }
            $sourcePath = realpath($record['source_path'] ?? '');
            if (! $sourcePath || ! is_file($sourcePath) || ! $this->isWithinRoots($sourcePath, $roots)) {
                throw new RuntimeException('A source PDF is missing or outside the declared source directories.');
            }
            if (filesize($sourcePath) !== (int) ($record['byte_size'] ?? -1)
                || ! hash_equals($record['sha256'], hash_file('sha256', $sourcePath))) {
                throw new RuntimeException('A source PDF changed after metadata preparation: '.basename($sourcePath));
            }
            if (! empty($record['cover_path'])) {
                $coverPath = realpath($record['cover_path']);
                if (! $coverPath || ! is_file($coverPath) || ! $this->isWithinRoot($coverPath, $coverRoot)) {
                    throw new RuntimeException('A prepared cover is missing or outside the cover directory.');
                }
                $image = getimagesize($coverPath);
                if (! $image || $image['mime'] !== 'image/jpeg' || $image[0] < 600 || $image[1] < 800
                    || filesize($coverPath) > 8 * 1024 * 1024) {
                    $record['cover_path'] = null;
                } else {
                    $record['cover_path'] = $coverPath;
                }
            }
            $record['source_path'] = $sourcePath;
            $record['filename'] = basename($sourcePath);
            $record['title'] = trim((string) ($record['title'] ?? '')) ?: pathinfo($sourcePath, PATHINFO_FILENAME);
            $record['author'] = trim((string) ($record['author'] ?? ''));
            $record['description'] = trim((string) ($record['description'] ?? ''));
            $record['category'] = trim((string) ($record['category'] ?? 'Christian Living')) ?: 'Christian Living';
            $books[] = $record;
            $hashes[$record['sha256']] = true;
            $categories[$record['category']] = ($categories[$record['category']] ?? 0) + 1;
        }

        $existingFiles = LibraryBookFile::query()
            ->join('library_books', 'library_books.id', '=', 'library_book_files.book_id')
            ->whereIn('library_book_files.sha256', array_keys($hashes))
            ->get(['library_book_files.sha256', 'library_book_files.book_id', 'library_books.cover_path'])
            ->keyBy('sha256');
        $existing = $existingFiles->keys()->all();
        $existing = array_fill_keys($existing, true);
        $seen = [];
        $newBooks = array_filter($books, function ($book) use (&$seen, $existing) {
            if (isset($seen[$book['sha256']]) || isset($existing[$book['sha256']])) {
                return false;
            }
            $seen[$book['sha256']] = true;
            return true;
        });
        $duplicatesSkipped = count($books) - count($newBooks);
        $coverUpdates = array_values(array_filter($books, fn ($book) => ! empty($book['cover_path'])
            && isset($existingFiles[$book['sha256']])
            && ! $existingFiles[$book['sha256']]->cover_path));

        $this->info(count($books).' PDFs prepared; '.count($newBooks).' new books; '
            .$duplicatesSkipped.' duplicate files skipped.');
        if ($coverUpdates) {
            $this->line(count($coverUpdates).' existing books will receive a generated first-page cover.');
        }
        foreach ($categories as $name => $count) {
            $this->line(sprintf('  %3d  %s', $count, $name));
        }
        $needsReview = count(array_filter($newBooks, fn ($book) => ($book['validation_status'] ?? '') !== 'valid'
            || (int) ($book['page_count'] ?? 0) < 1
            || (int) ($book['byte_size'] ?? 0) > (int) config('library.max_pdf_bytes')));
        $this->line($needsReview.' book(s) will remain unavailable to readers until their PDF is reviewed.');
        if ($this->option('dry-run')) {
            $this->info('Dry run complete; no database or library files were changed.');
            return self::SUCCESS;
        }

        $manifestHash = hash('sha256', $raw);
        $existingRun = DB::table('library_import_runs')->where('manifest_hash', $manifestHash)->first();
        if ($existingRun) {
            $this->info('This exact manifest has already been imported; no changes made.');
            return self::SUCCESS;
        }

        $root = config('library.root');
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('Could not create private library storage.');
        }
        $importDirectory = 'books/imports/'.$manifestHash;
        $absoluteImportDirectory = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $importDirectory);
        if (! is_dir($absoluteImportDirectory)
            && ! mkdir($absoluteImportDirectory, 0700, true)
            && ! is_dir($absoluteImportDirectory)) {
            throw new RuntimeException('Could not create the private import directory.');
        }

        $copiedPaths = [];
        try {
            DB::transaction(function () use ($newBooks, $coverUpdates, $existingFiles, $manifestHash, $root, $importDirectory, $absoluteImportDirectory, $duplicatesSkipped, &$copiedPaths) {
                $runId = DB::table('library_import_runs')->insertGetId([
                    'source_label' => 'E_books directory',
                    'manifest_hash' => $manifestHash,
                    'status' => 'complete',
                    'summary' => json_encode([
                        'books' => count($newBooks),
                        'duplicates_skipped' => $duplicatesSkipped,
                        'covers_added' => count($coverUpdates),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($newBooks as $bookData) {
                    $category = LibraryCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($bookData['category'])])->first();
                    if (! $category) {
                        $slugBase = Str::slug($bookData['category']) ?: 'category';
                        $slug = $slugBase;
                        for ($suffix = 2; LibraryCategory::where('slug', $slug)->exists(); $suffix++) {
                            $slug = $slugBase.'-'.$suffix;
                        }
                        $category = LibraryCategory::create(['name' => $bookData['category'], 'slug' => $slug]);
                    }

                    $slugBase = Str::limit(Str::slug($bookData['title']) ?: 'book', 220, '');
                    $slug = $slugBase.'-'.substr($bookData['sha256'], 0, 12);
                    $book = LibraryBook::create([
                        'slug' => $slug,
                        'title' => $bookData['title'],
                        'author' => $bookData['author'] ?: null,
                        'description' => $bookData['description'] ?: null,
                        'category_id' => $category->id,
                        'status' => 'draft',
                        'is_featured' => false,
                    ]);

                    $filename = $bookData['sha256'].'.pdf';
                    $relativePdfPath = $importDirectory.'/'.$filename;
                    $absolutePdfPath = $absoluteImportDirectory.DIRECTORY_SEPARATOR.$filename;
                    if (! is_file($absolutePdfPath)) {
                        if (! copy($bookData['source_path'], $absolutePdfPath)) {
                            throw new RuntimeException('Could not copy PDF into private library storage: '.$bookData['filename']);
                        }
                        $copiedPaths[] = $absolutePdfPath;
                    }

                    $coverPath = null;
                    if (! empty($bookData['cover_path'])) {
                        $coverPath = $this->storeCover($bookData, $book->id, $root, $copiedPaths);
                    }

                    $isValid = ($bookData['validation_status'] ?? '') === 'valid'
                        && (int) ($bookData['page_count'] ?? 0) > 0
                        && (int) $bookData['byte_size'] <= (int) config('library.max_pdf_bytes')
                        && empty($bookData['encrypted']);
                    $file = LibraryBookFile::create([
                        'book_id' => $book->id,
                        'version' => 1,
                        'path' => $relativePdfPath,
                        'original_filename' => $bookData['filename'],
                        'mime' => 'application/pdf',
                        'byte_size' => $bookData['byte_size'],
                        'sha256' => $bookData['sha256'],
                        'page_count' => $bookData['page_count'] ?: null,
                        'encrypted' => (bool) ($bookData['encrypted'] ?? false),
                        'validation_status' => $isValid ? 'valid' : 'review',
                    ]);
                    $book->update(['current_file_id' => $file->id, 'cover_path' => $coverPath]);

                    DB::table('library_import_records')->insert([
                        'run_id' => $runId,
                        'entity_type' => 'book',
                        'source_key' => $bookData['sha256'],
                        'source_hash' => $bookData['sha256'],
                        'destination' => (string) $book->id,
                    ]);
                }

                foreach ($coverUpdates as $bookData) {
                    $existingBook = (int) $existingFiles[$bookData['sha256']]->book_id;
                    $coverPath = $this->storeCover($bookData, $existingBook, $root, $copiedPaths);
                    LibraryBook::whereKey($existingBook)
                        ->whereNull('cover_path')
                        ->update(['cover_path' => $coverPath]);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($copiedPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            throw $exception;
        }

        $this->info('Import complete. New books are drafts; duplicate content was skipped.');
        return self::SUCCESS;
    }

    private function isWithinRoots(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($this->isWithinRoot($path, $root)) {
                return true;
            }
        }

        return false;
    }

    private function storeCover(array $bookData, int $bookId, string $root, array &$copiedPaths): string
    {
        $coverDirectory = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'covers';
        if (! is_dir($coverDirectory) && ! mkdir($coverDirectory, 0700, true) && ! is_dir($coverDirectory)) {
            throw new RuntimeException('Could not create private cover storage.');
        }
        $coverFilename = 'bulk-'.$bookId.'-'.substr($bookData['sha256'], 0, 12).'.jpg';
        $absoluteCoverPath = $coverDirectory.DIRECTORY_SEPARATOR.$coverFilename;
        if (! copy($bookData['cover_path'], $absoluteCoverPath)) {
            throw new RuntimeException('Could not copy generated cover for '.$bookData['title'].'.');
        }
        $copiedPaths[] = $absoluteCoverPath;

        return 'covers/'.$coverFilename;
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        $path = strtolower(str_replace('\\', '/', $path));
        $root = rtrim(strtolower(str_replace('\\', '/', $root)), '/');

        return str_starts_with($path, $root.'/');
    }
}
