<?php
namespace App\Services\Library;

class PrivateFiles
{
    public static function path(string $relative): string
    {
        $root = realpath(config('library.root'));
        $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$relative) : false;
        abort_unless($root && $path && str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root).'/')
            && is_file($path) && is_readable($path), 503, 'This file is temporarily unavailable.');
        return $path;
    }
}
