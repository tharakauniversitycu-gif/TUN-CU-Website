<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class LibrarySnapshot extends Command
{
    protected $signature = 'library:snapshot {--source= : Legacy application directory} {--output= : New manifest JSON path} {--python=python : Python with pypdf installed} {--label=culibrary}';
    protected $description = 'Read legacy catalogue and hash every upload; never modify the source';

    public function handle(): int
    {
        $root = realpath($this->option('source') ?: '');
        if (!$root || !is_dir($root.'/uploads') || !$this->option('output') || file_exists($this->option('output'))) {
            $this->error('Provide a legacy source with uploads and a new output file.'); return 1;
        }
        $cfg = config('database.connections.mysql');
        $cfg['database'] = env('LIBRARY_LEGACY_DATABASE', 'culibrary_db');
        $cfg['username'] = env('LIBRARY_LEGACY_USERNAME', $cfg['username']);
        $cfg['password'] = env('LIBRARY_LEGACY_PASSWORD', $cfg['password']);
        config(['database.connections.library_source' => $cfg]);
        $db = DB::connection('library_source');
        $db->statement('SET TRANSACTION READ ONLY'); $db->beginTransaction();
        try {
            $categories = $db->table('categories')->orderBy('id')->get(['id', 'name', 'slug'])->toArray();
            $books = $db->table('books')->orderBy('id')->get(['id', 'title', 'author', 'description', 'category_id', 'cover_image', 'file_path', 'upload_date'])->toArray();
        } finally { $db->rollBack(); }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/uploads', \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (!$f->isFile() || $f->isLink()) throw new \RuntimeException('Unexpected source entry.');
            $relative = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            $entry = ['path' => $relative, 'bytes' => $f->getSize(), 'sha256' => hash_file('sha256', $f->getPathname())];
            if (strtolower($f->getExtension()) === 'pdf') {
                $p = new Process([$this->option('python'), base_path('scripts/inspect-pdf.py'), $f->getPathname()]);
                $p->setTimeout(90); $p->mustRun();
                $entry['pdf'] = json_decode(trim($p->getOutput()), true, flags: JSON_THROW_ON_ERROR);
            }
            $files[$relative] = $entry;
        }
        ksort($files);
        $manifest = ['format' => 1, 'label' => $this->option('label'), 'source_database' => $cfg['database'],
            'categories' => $categories, 'books' => $books, 'files' => array_values($files)];
        file_put_contents($this->option('output'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info(count($books).' books, '.count($categories).' categories, '.count($files).' files recorded. Source unchanged.');
        return 0;
    }
}
