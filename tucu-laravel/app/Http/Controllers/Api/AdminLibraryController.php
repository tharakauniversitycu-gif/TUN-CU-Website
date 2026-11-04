<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryBookFile;
use App\Models\LibraryCategory;
use App\Services\Library\PdfCoverGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminLibraryController extends Controller
{
    public function index()
    {
        $filters = request()->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'category_id' => ['nullable', 'integer', 'exists:library_categories,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'sort' => ['nullable', Rule::in(['updated', 'title', 'oldest'])],
        ]);

        $query = LibraryBook::with(['category:id,name', 'currentFile', 'files' => fn ($query) => $query->orderByDesc('version')]);
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($builder) => $builder->where('title', 'like', $term)
                ->orWhere('author', 'like', $term)
                ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $term)));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        match ($filters['sort'] ?? 'updated') {
            'title' => $query->orderBy('title'),
            'oldest' => $query->orderBy('created_at'),
            default => $query->orderByDesc('updated_at'),
        };
        $books = $query
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25)
            ->through(fn (LibraryBook $book) => $this->bookData($book));

        return response()->json([
            'books' => $books,
            'categories' => LibraryCategory::orderBy('name')->get(['id', 'name', 'slug']),
            'counts' => [
                'all' => LibraryBook::count(),
                'draft' => LibraryBook::where('status', 'draft')->count(),
                'published' => LibraryBook::where('status', 'published')->count(),
                'archived' => LibraryBook::where('status', 'archived')->count(),
            ],
            'upload_limit_bytes' => min(
                (int) config('library.max_pdf_bytes'),
                $this->parseIniBytes((string) ini_get('upload_max_filesize')),
                $this->parseIniBytes((string) ini_get('post_max_size')),
            ),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);
        $slug = Str::slug($validated['name']);
        if ($slug === '') {
            throw ValidationException::withMessages(['name' => 'Enter a category name containing letters or numbers.']);
        }
        if (LibraryCategory::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'A category with this name already exists.']);
        }

        $category = LibraryCategory::create([
            'name' => trim($validated['name']),
            'slug' => $slug,
        ]);

        return response()->json(['category' => $category->only(['id', 'name', 'slug'])], 201);
    }

    public function store(Request $request, PdfCoverGenerator $coverGenerator)
    {
        $this->assertPostSize($request);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', 'exists:library_categories,id'],
            'is_featured' => ['sometimes', 'boolean'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:'.intdiv((int) config('library.max_pdf_bytes'), 1024)],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'pdf.uploaded' => 'The PDF upload did not reach the server. Check the server upload_max_filesize and post_max_size limits, then try again.',
            'pdf.max' => 'The PDF exceeds the library upload limit.',
        ]);

        $book = LibraryBook::create([
            'title' => trim($validated['title']),
            'author' => $validated['author'] ?? null,
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'slug' => Str::slug($validated['title']).'-'.Str::lower(Str::random(8)),
            'status' => 'draft',
            'is_featured' => $request->boolean('is_featured'),
        ]);

        $file = null;
        $coverPath = null;
        try {
            $file = $this->storeEdition($validated['pdf'], $book, 1, $coverGenerator);
            $coverPath = $file['cover_path'];
            if ($request->hasFile('cover')) {
                $this->removeFile($coverPath);
                $coverPath = $this->storeCover($request->file('cover'));
            }

            DB::transaction(function () use ($book, $file, $coverPath) {
                $record = LibraryBookFile::create($file['record']);
                $book->update(['current_file_id' => $record->id, 'cover_path' => $coverPath]);
            });
        } catch (\Throwable $exception) {
            if ($file) {
                $this->removeFile($file['record']['path']);
                $this->removeFile($coverPath);
            }
            $book->delete();
            throw $exception;
        }

        return response()->json(['book' => $this->bookData($book->fresh(['category', 'currentFile', 'files']))], 201);
    }

    public function update(Request $request, int $book)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:library_categories,id'],
            'is_featured' => ['sometimes', 'boolean'],
            'cover' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
        $model = LibraryBook::findOrFail($book);
        if (isset($validated['title'])) {
            $validated['title'] = trim($validated['title']);
        }
        $oldCover = $model->cover_path;
        if ($request->hasFile('cover')) {
            $validated['cover_path'] = $this->storeCover($request->file('cover'));
            unset($validated['cover']);
        }
        try {
            $model->update($validated);
        } catch (\Throwable $exception) {
            if ($request->hasFile('cover')) {
                $this->removeFile($validated['cover_path']);
            }
            throw $exception;
        }
        if ($request->hasFile('cover') && $oldCover !== $model->cover_path) {
            $this->removeFile($oldCover);
        }

        return response()->json(['book' => $this->bookData($model->fresh(['category', 'currentFile', 'files']))]);
    }

    public function addEdition(Request $request, int $book, PdfCoverGenerator $coverGenerator)
    {
        $this->assertPostSize($request);
        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:'.intdiv((int) config('library.max_pdf_bytes'), 1024)],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:library_categories,id'],
            'is_featured' => ['sometimes', 'boolean'],
        ], [
            'pdf.uploaded' => 'The PDF upload did not reach the server. Check the server upload_max_filesize and post_max_size limits, then try again.',
            'pdf.max' => 'The PDF exceeds the library upload limit.',
        ]);
        $model = LibraryBook::with('currentFile')->findOrFail($book);
        $previousCoverPath = $model->cover_path;
        $version = (int) LibraryBookFile::where('book_id', $model->id)->max('version') + 1;
        $edition = $this->storeEdition($validated['pdf'], $model, $version, $coverGenerator);
        $coverPath = $edition['cover_path'];
        $metadata = array_intersect_key($validated, array_flip(['title', 'author', 'description', 'category_id', 'is_featured']));
        if (isset($metadata['title'])) {
            $metadata['title'] = trim($metadata['title']);
        }
        try {
            if ($request->hasFile('cover')) {
                $this->removeFile($coverPath);
                $coverPath = $this->storeCover($request->file('cover'));
            }

            DB::transaction(function () use ($model, $edition, $coverPath, $metadata) {
                $record = LibraryBookFile::create($edition['record']);
                $model->update($metadata + ['current_file_id' => $record->id, 'cover_path' => $coverPath]);
            });
        } catch (\Throwable $exception) {
            $this->removeFile($edition['record']['path']);
            if ($coverPath !== $model->cover_path) {
                $this->removeFile($coverPath);
            }
            throw $exception;
        }
        if ($previousCoverPath !== $coverPath) {
            $this->removeFile($previousCoverPath);
        }

        return response()->json(['book' => $this->bookData($model->fresh(['category', 'currentFile', 'files']))]);
    }

    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:library_books,id'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        $books = LibraryBook::with('currentFile')->whereIn('id', $validated['ids'])->get();
        if ($validated['status'] === 'published') {
            $invalid = $books->filter(fn (LibraryBook $book) => ! $book->currentFile
                || $book->currentFile->book_id !== $book->id
                || $book->currentFile->validation_status !== 'valid'
                || ! $book->currentFile->page_count);
            if ($invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'ids' => 'Remove books without a validated PDF edition before publishing this selection: '
                        .$invalid->pluck('title')->implode(', ').'.',
                ]);
            }
        }

        DB::transaction(function () use ($books, $validated) {
            foreach ($books as $book) {
                $book->update([
                    'status' => $validated['status'],
                    'published_at' => $validated['status'] === 'draft' ? null : ($book->published_at ?? now()),
                ]);
            }
        });

        return response()->json(['updated' => $books->count(), 'status' => $validated['status']]);
    }

    public function setStatus(Request $request, int $book)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);
        $model = LibraryBook::with('currentFile')->findOrFail($book);
        if ($validated['status'] === 'published'
            && (! $model->currentFile || $model->currentFile->book_id !== $model->id
                || $model->currentFile->validation_status !== 'valid' || ! $model->currentFile->page_count)) {
            throw ValidationException::withMessages(['status' => 'Add and validate a PDF edition before publishing this book.']);
        }

        $model->update([
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'draft' ? null : ($model->published_at ?? now()),
        ]);

        return response()->json(['book' => $this->bookData($model->fresh(['category', 'currentFile', 'files']))]);
    }

    private function storeEdition($upload, LibraryBook $book, int $version, PdfCoverGenerator $coverGenerator): array
    {
        $root = config('library.root');
        $directory = $root.DIRECTORY_SEPARATOR.'books';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the private library storage directory.');
        }

        $filename = Str::uuid().'.pdf';
        $absolutePath = $directory.DIRECTORY_SEPARATOR.$filename;
        $relativePath = 'books/'.$filename;
        if (! $upload->move($directory, $filename)) {
            throw new RuntimeException('Could not store the uploaded PDF.');
        }

        try {
            $metadata = $coverGenerator->generateWithMetadata($relativePath, $book->id);
        } catch (RuntimeException $exception) {
            $this->removeFile($relativePath);
            throw ValidationException::withMessages(['pdf' => $exception->getMessage()]);
        }

        return [
            'cover_path' => $metadata['path'],
            'record' => [
                'book_id' => $book->id,
                'version' => $version,
                'path' => $relativePath,
                'original_filename' => $upload->getClientOriginalName(),
                'mime' => 'application/pdf',
                'byte_size' => filesize($absolutePath),
                'sha256' => hash_file('sha256', $absolutePath),
                'page_count' => $metadata['page_count'],
                'encrypted' => false,
                'validation_status' => 'valid',
            ],
        ];
    }

    private function storeCover($upload): string
    {
        $directory = config('library.root').DIRECTORY_SEPARATOR.'covers';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the private library cover directory.');
        }
        $image = getimagesize($upload->getRealPath());
        if (! $image || ! in_array($image['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || $image[0] < 100 || $image[1] < 100 || $image[0] > 5000 || $image[1] > 7000) {
            throw ValidationException::withMessages(['cover' => 'Choose a valid JPEG, PNG, or WebP image between 100 × 100 and 5000 × 7000 pixels.']);
        }

        $extension = match ($image['mime']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'webp',
        };
        $filename = Str::uuid().'.'.$extension;
        $upload->move($directory, $filename);

        return 'covers/'.$filename;
    }

    private function removeFile(?string $relativePath): void
    {
        if ($relativePath) {
            $root = realpath(config('library.root'));
            $path = $root ? $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath) : false;
            $resolvedPath = $path ? realpath($path) : false;
            if ($resolvedPath && is_file($resolvedPath) && str_starts_with($resolvedPath, $root.DIRECTORY_SEPARATOR)) {
                unlink($path);
            }
        }
    }

    private function bookData(LibraryBook $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'slug' => $book->slug,
            'author' => $book->author,
            'description' => $book->description,
            'category' => $book->category?->only(['id', 'name']),
            'category_id' => $book->category_id,
            'status' => $book->status,
            'is_featured' => $book->is_featured,
            'published_at' => $book->published_at,
            'cover_url' => $book->cover_path ? '/api/library/books/'.$book->id.'/cover' : null,
            'current_file' => $book->currentFile?->only(['id', 'version', 'original_filename', 'byte_size', 'page_count', 'validation_status']),
            'versions' => $book->files->sortByDesc('version')->values()->map(fn (LibraryBookFile $file) => $file->only([
                'id', 'version', 'original_filename', 'byte_size', 'page_count', 'validation_status', 'created_at',
            ])),
            'updated_at' => $book->updated_at,
        ];
    }

    private function parseIniBytes(string $value): int
    {
        if (! preg_match('/^\s*(\d+(?:\.\d+)?)\s*([kmgt]?)b?\s*$/i', $value, $matches)) {
            return PHP_INT_MAX;
        }

        $factor = match (strtolower($matches[2])) {
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            't' => 1024 ** 4,
            default => 1,
        };

        return (int) min(PHP_INT_MAX, (float) $matches[1] * $factor);
    }

    private function assertPostSize(Request $request): void
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $postLimit = $this->parseIniBytes((string) ini_get('post_max_size'));
        abort_if($contentLength > 0 && $contentLength > $postLimit, 413,
            'This upload exceeds the PHP post_max_size limit. Increase post_max_size and try again.');
    }
}
