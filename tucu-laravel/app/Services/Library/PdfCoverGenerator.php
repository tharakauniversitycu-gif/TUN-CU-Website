<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PdfCoverGenerator
{
    public function generate(string $pdfPath, int $bookId): string
    {
        return $this->render($pdfPath, $bookId, false)['path'];
    }

    public function generateWithMetadata(string $pdfPath, int $bookId): array
    {
        return $this->render($pdfPath, $bookId, true);
    }

    private function render(string $pdfPath, int $bookId, bool $requireMetadata): array
    {
        $source = PrivateFiles::path($pdfPath);
        $root = realpath(config('library.root'));
        if (! $root) {
            throw new RuntimeException('The private library storage directory is unavailable.');
        }

        $directory = $root.DIRECTORY_SEPARATOR.'covers';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the private library cover directory.');
        }

        $relative = 'covers/book-'.$bookId.'.jpg';
        $target = $directory.DIRECTORY_SEPARATOR.'book-'.$bookId.'.jpg';
        $temporary = $directory.DIRECTORY_SEPARATOR.'.book-'.$bookId.'-'.bin2hex(random_bytes(8)).'.jpg';

        try {
            $result = Process::path(base_path())->timeout(90)->run([
                config('library.python_binary'),
                base_path('scripts/generate-pdf-cover.py'),
                $source,
                $temporary,
            ]);

            if (! $result->successful()) {
                $detail = trim($result->errorOutput());
                throw new RuntimeException('Could not generate the first-page cover for book '.$bookId
                    .($detail !== '' ? ': '.substr($detail, 0, 500) : '.'));
            }

            $metadata = json_decode(trim($result->output()), true);
            if ($requireMetadata && (! is_array($metadata) || ! isset($metadata['page_count']) || (int) $metadata['page_count'] < 1)) {
                throw new RuntimeException('The uploaded PDF could not be validated.');
            }

            $image = getimagesize($temporary);
            if (! $image || $image['mime'] !== 'image/jpeg' || $image[0] < 600 || $image[1] < 800
                || filesize($temporary) > 8 * 1024 * 1024) {
                throw new RuntimeException('The generated cover for book '.$bookId.' failed image validation.');
            }

            if (! rename($temporary, $target)) {
                throw new RuntimeException('Could not store the generated cover for book '.$bookId.'.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return ['path' => $relative, 'page_count' => (int) ($metadata['page_count'] ?? 0)];
    }
}
