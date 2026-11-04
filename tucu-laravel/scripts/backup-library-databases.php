<?php
// Local operator helper. Credentials go to the child environment, never command output.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$executable = $argv[1] ?? '';
if (!is_file($executable)) throw new RuntimeException('Pass a mysqldump executable.');
$cfg = config('database.connections.mysql');
$folder = storage_path('app/private/library-backups/'.gmdate('Ymd-His'));
mkdir($folder, 0700, true);
foreach (array_unique([$cfg['database'], env('LIBRARY_LEGACY_DATABASE','culibrary_db')]) as $database) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) throw new RuntimeException('Unexpected database name.');
    $path = $folder.'/'.$database.'.sql';
    $process = new Symfony\Component\Process\Process([$executable, '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'],
        '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--result-file='.$path, $database],
        null, ['MYSQL_PWD'=>$cfg['password']]);
    $process->setTimeout(120); $process->mustRun();
    if (filesize($path) < 100) throw new RuntimeException('Backup is unexpectedly empty.');
    echo $database.': backup saved privately; SHA-256 '.hash_file('sha256',$path).PHP_EOL;
}
echo 'Backup folder: '.$folder.PHP_EOL;
