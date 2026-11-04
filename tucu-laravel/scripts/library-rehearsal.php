<?php
// Creates an isolated SQLite rehearsal. Never reads or writes member rows in MySQL.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$name = $argv[1] ?? 'phase4';
if (!preg_match('/^[a-z0-9-]+$/', $name)) throw new RuntimeException('Invalid rehearsal name.');
$root = storage_path('testing/'.$name);
if (!is_dir($root)) mkdir($root, 0700, true);
$database = $root.'/database.sqlite';
if (!file_exists($database)) touch($database);
config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>$database,
    'library.root'=>$root.'/library', 'cache.default'=>'array', 'session.driver'=>'array']);
Illuminate\Support\Facades\DB::purge();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);
$manifest = storage_path('app/private/library-backups/manifest.json');
foreach (['--dry-run', '--apply', '--apply'] as $mode) {
    $result = Illuminate\Support\Facades\Artisan::call('library:import-legacy', ['manifest'=>$manifest, '--source'=>'C:/laragon/www/culibrary', $mode=>true]);
    echo Illuminate\Support\Facades\Artisan::output();
    if ($result !== 0) exit($result);
}
// This synthetic account and publication exist only in the named rehearsal database.
$member = App\Models\Member::firstOrCreate(['email'=>'reader@example.test'], ['full_name'=>'Rehearsal Reader', 'password'=>'Library-Test-2026!', 'status'=>'active']);
App\Models\LibraryBook::whereIn('legacy_id', [1,2,3])->whereHas('currentFile', fn($q)=>$q->where('validation_status','valid'))->update(['status'=>'published', 'published_at'=>now()]);
echo 'Rehearsal ready: '.$name.'. All five legacy books retained; identified readable titles published only here.'.PHP_EOL;
