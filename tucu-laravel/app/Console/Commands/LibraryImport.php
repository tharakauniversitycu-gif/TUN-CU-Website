<?php
namespace App\Console\Commands;

use App\Models\LibraryBook;
use App\Models\LibraryBookFile;
use App\Models\LibraryCategory;
use App\Services\Library\PdfCoverGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LibraryImport extends Command
{
    protected $signature = 'library:import-legacy {manifest} {--source= : Legacy application directory} {--dry-run} {--apply}';
    protected $description = 'Verify and copy a legacy snapshot into private storage, importing all books as drafts';

    public function handle(): int
    {
        if ((bool)$this->option('apply') === (bool)$this->option('dry-run')) {
            $this->error('Choose exactly one of --dry-run or --apply.'); return 1;
        }
        $raw = file_get_contents($this->argument('manifest'));
        $m = json_decode($raw, true, flags: JSON_THROW_ON_ERROR); $hash = hash('sha256', $raw);
        $source = realpath($this->option('source') ?: '');
        if (!$source || ($m['format'] ?? null) !== 1) throw new \RuntimeException('Invalid source or manifest.');
        $root = str_replace('\\', '/', config('library.root'));
        $sourceNormalized = str_replace('\\', '/', $source);
        if (!preg_match('~^(?:[A-Za-z]:/|/)~', $root) || str_contains($root, '..') || str_starts_with(strtolower($root).'/', strtolower($sourceNormalized).'/') || str_starts_with(strtolower($sourceNormalized).'/', strtolower($root).'/')) throw new \RuntimeException('Private destination must be absolute and separate from source.');
        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) === 'mysql' && ($connection['database'] ?? null) === $m['source_database']) throw new \RuntimeException('Source and destination databases must differ.');
        $files = [];
        foreach ($m['files'] as $f) {
            $rel = $f['path'];
            if (!preg_match('~^uploads/[A-Za-z0-9_./ -]+$~', $rel) || str_contains($rel, '..')) throw new \RuntimeException('Unsafe source path.');
            $path = realpath($source.'/'.$rel);
            if (!$path || !str_starts_with(str_replace('\\', '/', $path), $sourceNormalized.'/') || !hash_equals($f['sha256'], hash_file('sha256', $path)) || filesize($path) !== $f['bytes']) throw new \RuntimeException('Source file changed: '.$rel);
            $files[$rel] = $f;
        }
        $actual = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source.'/uploads', \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) $actual[] = str_replace('\\', '/', substr($f->getPathname(), strlen($source) + 1));
        }
        sort($actual); $expected = array_keys($files); sort($expected);
        if ($actual !== $expected) throw new \RuntimeException('Upload inventory changed. Generate a new manifest.');
        foreach ($m['books'] as $b) {
            if (!isset($files[$b['file_path']]['pdf'])) throw new \RuntimeException('Referenced PDF is missing from manifest.');
        }
        $this->info(count($m['books']).' draft books; '.count($m['categories']).' categories; '.count($files).' hash-verified archive files.');
        if ($this->option('dry-run')) { $this->info('Dry run: no destination writes.'); return 0; }
        $existing = DB::table('library_import_runs')->where('manifest_hash', $hash)->first();
        $prefix = 'legacy/'.$hash;
        if ($existing) {
            foreach ($files as $rel => $f) {
                $destination = $root.'/'.$prefix.'/'.$rel;
                if (!is_file($destination) || !hash_equals($f['sha256'], hash_file('sha256', $destination))) throw new \RuntimeException('Imported archive failed verification.');
            }
            $this->info('Already imported; archive verified, no changes.'); return 0;
        }
        if (DB::table('library_import_runs')->exists() || LibraryBook::whereNotNull('legacy_id')->exists()) throw new \RuntimeException('Different source snapshot or existing legacy records require reconciliation.');
        // Immutable hash namespace makes interrupted copies resumable; never unlink source data.
        foreach ($files as $rel => $f) {
            $target = $root.'/'.$prefix.'/'.$rel;
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
            if (file_exists($target) && !hash_equals($f['sha256'], hash_file('sha256', $target))) throw new \RuntimeException('Conflicting staged file.');
            if (!file_exists($target) && !copy($source.'/'.$rel, $target)) throw new \RuntimeException('Copy failed.');
            if (!hash_equals($f['sha256'], hash_file('sha256', $target))) throw new \RuntimeException('Copy verification failed.');
        }
        file_put_contents($root.'/'.$prefix.'/manifest.json', $raw);
        DB::transaction(function () use ($m, $hash, $prefix, $files, $root) {
            $run = DB::table('library_import_runs')->insertGetId(['source_label' => $m['label'], 'manifest_hash' => $hash,
                'status' => 'complete', 'summary' => json_encode(['books' => count($m['books']), 'files' => count($files)]), 'created_at' => now(), 'updated_at' => now()]);
            $categories = [];
            foreach ($m['categories'] as $c) {
                $row = LibraryCategory::create(['legacy_id' => $c['id'], 'name' => $c['name'], 'slug' => ($c['slug'] ?: Str::slug($c['name'])).'-'.$c['id']]);
                $categories[$c['id']] = $row->id;
                $this->journal($run, 'category', (string)$c['id'], hash('sha256', json_encode($c)), (string)$row->id);
            }
            foreach ($m['books'] as $b) {
                $f = $files[$b['file_path']];
                $book = LibraryBook::create(['legacy_id' => $b['id'], 'slug' => (Str::slug($b['title']) ?: 'book').'-'.$b['id'],
                    'title' => $b['title'], 'author' => $b['author'], 'description' => $b['description'],
                    'category_id' => $b['category_id'] ? ($categories[$b['category_id']] ?? throw new \RuntimeException('Unknown category.')) : null,
                    'status' => 'draft', 'legacy_uploaded_at' => $b['upload_date']]);
                $file = LibraryBookFile::create(['book_id' => $book->id, 'version' => 1, 'path' => $prefix.'/'.$b['file_path'],
                    'original_filename' => basename($b['file_path']), 'byte_size' => $f['bytes'], 'sha256' => $f['sha256'],
                    'page_count' => $f['pdf']['page_count'], 'encrypted' => $f['pdf']['encrypted'],
                    'validation_status' => $f['bytes'] <= config('library.max_pdf_bytes') ? $f['pdf']['validation_status'] : 'review']);
                // Only exact, valid cover references become re-encoded derivatives. Broken references stay placeholders.
                $cover = $b['cover_image']; $coverPath = null;
                if ($cover && isset($files[$cover]) && $files[$cover]['bytes'] <= 5 * 1024 * 1024) {
                    $image = @imagecreatefromstring(file_get_contents($root.'/'.$prefix.'/'.$cover));
                    if ($image) {
                        $coverPath = $prefix.'/cover-'.$book->id.'.jpg';
                        imagejpeg($image, $root.'/'.$coverPath, 85); imagedestroy($image);
                    }
                }
                if (!$coverPath && $file->validation_status === 'valid' && $file->page_count > 0) {
                    $coverPath = app(PdfCoverGenerator::class)->generate($file->path, $book->id);
                }
                $book->update(['current_file_id' => $file->id, 'cover_path' => $coverPath]);
                $this->journal($run, 'book', (string)$b['id'], hash('sha256', json_encode($b)), (string)$book->id);
            }
            foreach ($files as $rel => $f) $this->journal($run, 'file', $rel, $f['sha256'], $prefix.'/'.$rel);
        });
        $this->info('Import complete. All books remain drafts; no member or administrator accounts changed.');
        return 0;
    }

    private function journal(int $run, string $type, string $key, string $hash, string $destination): void
    {
        DB::table('library_import_records')->insert(['run_id' => $run, 'entity_type' => $type, 'source_key' => $key,
            'source_hash' => $hash, 'destination' => $destination]);
    }
}
