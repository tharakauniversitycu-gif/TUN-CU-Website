<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryCategory;
use App\Services\Library\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LibraryController extends Controller
{
    private function book(int $id): LibraryBook
    {
        return LibraryBook::with(['category', 'currentFile'])->published()->findOrFail($id);
    }

    private function file(LibraryBook $book)
    {
        $file = $book->currentFile;
        abort_unless($file && $file->book_id === $book->id && $file->validation_status === 'valid' && $file->page_count > 0, 503,
            'This book is not ready for reading.');
        return $file;
    }

    public function categories()
    {
        return ['data' => LibraryCategory::orderBy('name')->get(['id', 'name', 'slug'])];
    }

    public function index(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:200', 'category' => 'nullable|string|max:100',
            'sort' => ['nullable', Rule::in(['featured', 'title', 'recent'])], 'page' => 'nullable|integer|min:1']);
        $q = LibraryBook::with(['category', 'currentFile'])->published();
        if (!empty($v['q'])) $q->where(fn ($q) => $q->where('title', 'like', '%'.$v['q'].'%')->orWhere('author', 'like', '%'.$v['q'].'%'));
        if (!empty($v['category'])) $q->whereHas('category', fn ($q) => $q->where('slug', $v['category']));
        match ($v['sort'] ?? 'featured') {
            'title' => $q->orderBy('title'), 'recent' => $q->orderByDesc('published_at'),
            default => $q->orderByDesc('is_featured')->orderByDesc('published_at'),
        };
        return $q->orderBy('id')->paginate(20)->through(fn ($b) => $b->publicData());
    }

    public function show(int $book) { return ['data' => $this->book($book)->publicData()]; }

    public function cover(int $book)
    {
        $b = $this->book($book);
        abort_unless($b->cover_path, 404);
        $path = PrivateFiles::path($b->cover_path);
        abort_unless(in_array(mime_content_type($path), ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        return response()->file($path, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function memberBook(Request $r, int $book)
    {
        $b = $this->book($book);
        return ['data' => $this->state($r->user()->id, $b)];
    }

    private function state(int $member, LibraryBook $b): array
    {
        return ['liked' => DB::table('library_likes')->where(['member_id' => $member, 'book_id' => $b->id])->exists(),
            'progress' => DB::table('library_reading_progress')->where(['member_id' => $member, 'book_id' => $b->id, 'file_id' => $b->current_file_id])->first(),
            'file_id' => $b->current_file_id,
            'previous_version' => DB::table('library_reading_progress')->where(['member_id' => $member, 'book_id' => $b->id])->where('file_id', '!=', $b->current_file_id)->exists()];
    }

    public function shelf(Request $r)
    {
        $v = $r->validate(['collection' => ['nullable', Rule::in(['in_progress', 'completed', 'liked', 'downloads'])], 'page' => 'nullable|integer|min:1']);
        $collection = $v['collection'] ?? 'in_progress'; $member = $r->user()->id;
        $q = LibraryBook::with(['category', 'currentFile'])->published();
        if (in_array($collection, ['in_progress', 'completed'], true)) {
            $q->whereExists(fn ($q) => $q->selectRaw('1')->from('library_reading_progress as p')
                ->whereColumn('p.book_id', 'library_books.id')->whereColumn('p.file_id', 'library_books.current_file_id')
                ->where('p.member_id', $member)->where('p.status', $collection));
            $q->orderByDesc(DB::table('library_reading_progress as p')->select('last_read_at')->whereColumn('p.book_id', 'library_books.id')
                ->whereColumn('p.file_id', 'library_books.current_file_id')->where('p.member_id', $member)->limit(1));
        } else {
            $table = $collection === 'liked' ? 'library_likes' : 'library_downloads';
            $q->whereIn('id', DB::table($table)->select('book_id')->where('member_id', $member));
        }
        return $q->orderBy('id')->paginate(20)->through(fn ($b) => array_merge($b->publicData(), ['member_state' => $this->state($member, $b)]));
    }

    public function like(Request $r, int $book)
    {
        $this->book($book);
        $key = ['member_id' => $r->user()->id, 'book_id' => $book];
        if ($r->isMethod('delete')) DB::table('library_likes')->where($key)->delete();
        else DB::table('library_likes')->insertOrIgnore($key + ['created_at' => now()]);
        return ['liked' => !$r->isMethod('delete')];
    }

    public function progress(Request $r, int $book)
    {
        $v = $r->validate(['file_id' => 'required|integer|min:1', 'expected_revision' => 'required|integer|min:0',
            'current_page' => 'required|integer|min:1', 'page_offset' => 'sometimes|numeric|between:0,1',
            'status' => ['required', Rule::in(['in_progress', 'completed'])], 'member_id' => 'prohibited']);
        return DB::transaction(function () use ($r, $book, $v) {
            // Serialise first inserts as well as updates for the same owner.
            DB::table('members')->where('id', $r->user()->id)->lockForUpdate()->first();
            $b = $this->book($book); $file = $this->file($b);
            if ($file->id !== $v['file_id']) return response()->json(['message' => 'A new edition is available. Reload the book.', 'file_id' => $file->id], 409);
            abort_if($v['current_page'] > $file->page_count, 422, 'Page is outside this book.');
            $key = ['member_id' => $r->user()->id, 'book_id' => $book, 'file_id' => $file->id];
            $old = DB::table('library_reading_progress')->where($key)->first();
            if (($old->revision ?? 0) !== $v['expected_revision']) return response()->json([
                'message' => 'Your reading position changed in another session.', 'progress' => $old], 409);
            $data = ['current_page' => $v['current_page'], 'furthest_page' => max($old->furthest_page ?? 1, $v['current_page']),
                'page_offset' => $v['page_offset'] ?? 0, 'status' => $v['status'], 'revision' => ($old->revision ?? 0) + 1,
                'started_at' => $old->started_at ?? now(), 'last_read_at' => now(),
                'completed_at' => $v['status'] === 'completed' ? ($old->completed_at ?? now()) : null];
            DB::table('library_reading_progress')->updateOrInsert($key, $data);
            return ['progress' => DB::table('library_reading_progress')->where($key)->first()];
        });
    }

    public function content(Request $r, int $book)
    {
        $r->validate(['version' => 'required|integer|min:1']);
        $file = $this->file($this->book($book));
        abort_unless($file->id === (int) $r->query('version'), 409, 'This edition has changed. Reload the book.');
        return response()->file(PrivateFiles::path($file->path), ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    public function download(Request $r, int $book)
    {
        $v = $r->validate(['request_key' => 'required|uuid']);
        $b = $this->book($book); $file = $this->file($b); $path = PrivateFiles::path($file->path);
        $key = ['member_id' => $r->user()->id, 'request_key' => $v['request_key']];
        DB::table('library_downloads')->insertOrIgnore($key + ['book_id' => $book, 'file_id' => $file->id, 'requested_at' => now()]);
        $record = DB::table('library_downloads')->where($key)->first();
        abort_unless($record->book_id === $book && $record->file_id === $file->id, 409, 'This download request belongs to a different book edition.');
        return response()->download($path, 'cu-book-'.$book.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }
}
