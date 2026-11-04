<?php
namespace Tests\Feature;

use App\Models\{Member, Admin, LibraryBook, LibraryBookFile, LibraryCategory};
use App\Services\Library\PdfCoverGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;
    private function member(string $status = 'active'): Member
    {
        return Member::create(['full_name' => 'Library Test', 'email' => uniqid().'@example.test', 'password' => 'test-password', 'status' => $status]);
    }
    private function token($owner): string { return $owner->createToken('test')->plainTextToken; }
    private function auth(string $token): array { $this->app['auth']->forgetGuards(); return ['Authorization' => 'Bearer '.$token]; }
    private function book(string $status = 'published'): LibraryBook
    {
        $b = LibraryBook::create(['title' => 'Test Faith', 'slug' => uniqid('book-'), 'status' => $status]);
        $root = config('library.root'); if (!is_dir($root)) mkdir($root, 0700, true);
        file_put_contents($root.'/fixture.pdf', "%PDF-1.4\nSynthetic API bytes\n%%EOF");
        $f = LibraryBookFile::create(['book_id' => $b->id, 'version' => 1, 'path' => 'fixture-'.$b->id.'.pdf', 'original_filename' => 'test.pdf', 'byte_size' => 35, 'sha256' => str_repeat('a',64), 'page_count' => 10, 'validation_status' => 'valid']);
        copy($root.'/fixture.pdf', $root.'/'.$f->path);
        $b->update(['current_file_id' => $f->id]); return $b;
    }
    public function test_public_catalogue_never_exposes_drafts_or_storage_paths(): void
    {
        $b = $this->book(); $draft = $this->book('draft');
        $response = $this->getJson('/api/library/books?q=Faith')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $b->id);
        $this->assertStringNotContainsString('fixture-', $response->getContent());
        $this->getJson('/api/library/books/'.$draft->id)->assertNotFound();
        $this->getJson('/api/library/books?q='.str_repeat('x',201))->assertUnprocessable();
        $this->getJson('/api/library/books?sort=bad')->assertUnprocessable();
    }
    public function test_guest_and_admin_tokens_cannot_read_member_content(): void
    {
        $b = $this->book();
        $this->getJson('/api/library/books/'.$b->id.'/content?version='.$b->current_file_id)->assertUnauthorized();
        $this->getJson('/api/library/me/books')->assertUnauthorized();
        $this->putJson('/api/library/me/books/'.$b->id.'/like')->assertUnauthorized();
        $admin = Admin::create(['username' => 'staff', 'password' => 'test-password']);
        $this->getJson('/api/library/me/books', $this->auth($this->token($admin)))->assertUnauthorized();
        $memberToken = $this->token($this->member());
        $this->getJson('/api/admin/stats', $this->auth($memberToken))->assertUnauthorized();
        $this->getJson('/api/admin/library')->assertUnauthorized();
        $this->getJson('/api/admin/library', $this->auth($memberToken))->assertUnauthorized();
    }
    public function test_admin_can_manage_categories_books_editions_and_publication(): void
    {
        $root = storage_path('framework/testing/library-admin-'.uniqid());
        mkdir($root, 0700, true);
        config(['library.root' => $root]);
        $admin = Admin::create(['username' => 'library-admin', 'password' => 'test-password']);
        $headers = $this->auth($this->token($admin));
        Process::fake(function ($process) {
            $image = imagecreatetruecolor(600, 800);
            imagejpeg($image, $process->command[3], 88);
            imagedestroy($image);
            return new FakeProcessResult('', 0, '{"page_count": 12}');
        });

        try {
            $this->postJson('/api/admin/library/categories', ['name' => 'Reading & Discipleship'], $headers)
                ->assertCreated()->assertJsonPath('category.slug', 'reading-discipleship');
            $this->postJson('/api/admin/library/categories', ['name' => 'Reading & Discipleship'], $headers)->assertUnprocessable();
            $categoryId = LibraryCategory::firstOrFail()->id;
            $pdf = fn ($name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.7\nSynthetic PDF\n%%EOF");

            $response = $this->post('/api/admin/library/books', [
                'title' => 'A faithful book',
                'author' => 'TUCU',
                'category_id' => $categoryId,
                'is_featured' => '1',
                'pdf' => $pdf('faithful-book.pdf'),
            ], $headers + ['Accept' => 'application/json'])->assertCreated();
            $bookId = $response->json('book.id');
            $this->assertSame('draft', $response->json('book.status'));
            $this->assertSame(12, $response->json('book.current_file.page_count'));
            $storedPath = LibraryBookFile::where('book_id', $bookId)->value('path');
            $this->assertFileExists($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storedPath));

            $this->getJson('/api/admin/library', $headers)->assertOk()
                ->assertJsonPath('books.data.0.id', $bookId)->assertJsonPath('books.data.0.versions.0.version', 1);
            $this->putJson('/api/admin/library/books/'.$bookId, ['description' => 'An encouraging book.'], $headers)
                ->assertOk()->assertJsonPath('book.description', 'An encouraging book.');
            $this->putJson('/api/admin/library/books/'.$bookId.'/status', ['status' => 'published'], $headers)
                ->assertOk()->assertJsonPath('book.status', 'published');
            $this->getJson('/api/library/books')->assertOk()->assertJsonPath('total', 1);

            $this->post('/api/admin/library/books/'.$bookId.'/editions', ['pdf' => $pdf('faithful-book-v2.pdf')],
                $headers + ['Accept' => 'application/json'])->assertOk()
                ->assertJsonPath('book.current_file.version', 2)
                ->assertJsonPath('book.versions.0.version', 2);
            $this->putJson('/api/admin/library/books/'.$bookId.'/status', ['status' => 'archived'], $headers)
                ->assertOk()->assertJsonPath('book.status', 'archived');
            $this->getJson('/api/library/books')->assertOk()->assertJsonPath('total', 0);
        } finally {
            File::deleteDirectory($root);
        }
    }
    public function test_status_is_rechecked_for_existing_tokens_and_associates_can_read(): void
    {
        $member = $this->member(); $token = $this->token($member);
        $this->getJson('/api/library/me/books', $this->auth($token))->assertOk();
        $member->update(['status' => 'suspended']);
        $this->getJson('/api/library/me/books', $this->auth($token))->assertForbidden();
        $this->getJson('/api/library/me/books', $this->auth($this->token($this->member('associate'))))->assertOk();
    }
    public function test_progress_is_private_bounded_and_revision_controlled(): void
    {
        $b = $this->book(); $a = $this->token($this->member()); $other = $this->token($this->member());
        $body = ['file_id' => $b->current_file_id, 'current_page' => 5, 'expected_revision' => 0, 'status' => 'in_progress'];
        $url = '/api/library/me/books/'.$b->id.'/progress';
        $this->putJson($url, $body, $this->auth($a))->assertOk()->assertJsonPath('progress.revision',1);
        $this->putJson($url, $body, $this->auth($a))->assertConflict()->assertJsonPath('progress.current_page',5);
        $this->getJson('/api/library/me/books/'.$b->id, $this->auth($other))->assertOk()->assertJsonPath('data.progress',null);
        $this->getJson('/api/library/me/books', $this->auth($other))->assertJsonPath('total',0);
        $body['expected_revision'] = 1; $body['current_page'] = 11;
        $this->putJson($url, $body, $this->auth($a))->assertUnprocessable();
        $body['current_page'] = 2; $body['member_id'] = 123;
        $this->putJson($url, $body, $this->auth($a))->assertUnprocessable(); unset($body['member_id']);
        $this->putJson($url, $body, $this->auth($a))->assertOk()->assertJsonPath('progress.furthest_page',5)->assertJsonPath('progress.current_page',2);
        $body['expected_revision'] = 2; $body['status'] = 'completed';
        $this->putJson($url, $body, $this->auth($a))->assertOk();
        $this->getJson('/api/library/me/books?collection=completed', $this->auth($a))->assertJsonPath('total',1);
    }
    public function test_likes_downloads_and_file_access(): void
    {
        $b = $this->book(); $member = $this->member(); $token = $this->token($member);
        for ($i=0; $i<2; $i++) $this->putJson('/api/library/me/books/'.$b->id.'/like', [], $this->auth($token))->assertOk();
        $this->assertDatabaseCount('library_likes',1);
        $this->getJson('/api/library/books/'.$b->id.'/content?version='.$b->current_file_id, $this->auth($token))->assertOk()->assertHeader('Content-Type','application/pdf')->assertHeader('Cache-Control','no-store, private');
        $this->get('/api/library/books/'.$b->id.'/content?version='.$b->current_file_id, $this->auth($token) + ['Range'=>'bytes=0-3'])
            ->assertStatus(206)->assertHeader('Content-Range','bytes 0-3/34')->assertHeader('Content-Length','4');
        $this->getJson('/api/library/books/'.$b->id.'/content?version=999', $this->auth($token))->assertConflict();
        $key = ['request_key' => '11111111-1111-4111-8111-111111111111'];
        for ($i=0; $i<2; $i++) $this->postJson('/api/library/books/'.$b->id.'/download', $key, $this->auth($token))->assertOk();
        $this->assertDatabaseCount('library_downloads',1);
        $this->getJson('/api/library/me/books?collection=downloads', $this->auth($token))->assertJsonPath('total',1);
        $b->update(['status'=>'archived']);
        $this->getJson('/api/library/books/'.$b->id.'/content?version='.$b->current_file_id, $this->auth($token))->assertNotFound();
        $member->delete(); $this->assertDatabaseCount('library_likes',0); $this->assertDatabaseCount('library_downloads',0);
    }
    public function test_missing_or_cross_book_files_fail_closed(): void
    {
        $b = $this->book(); $other = $this->book(); $token = $this->token($this->member());
        $b->update(['current_file_id' => $other->current_file_id]);
        $this->getJson('/api/library/books/'.$b->id.'/content?version='.$other->current_file_id, $this->auth($token))->assertStatus(503);
        $other->currentFile->update(['path' => '../outside.pdf']);
        $this->getJson('/api/library/books/'.$other->id.'/content?version='.$other->current_file_id, $this->auth($token))->assertStatus(503);
    }
    public function test_pdf_cover_generator_creates_a_valid_private_cover(): void
    {
        $b = $this->book();
        Process::fake(function ($process) {
            $image = imagecreatetruecolor(600, 800);
            imagejpeg($image, $process->command[3], 88);
            imagedestroy($image);
            return new FakeProcessResult;
        });

        $cover = null;
        try {
            $path = app(PdfCoverGenerator::class)->generate($b->currentFile->path, $b->id);
            $cover = config('library.root').DIRECTORY_SEPARATOR.$path;

            $this->assertSame('covers/book-'.$b->id.'.jpg', $path);
            $image = getimagesize($cover);
            $this->assertSame('image/jpeg', $image['mime']);
            $this->assertSame([600, 800], [$image[0], $image[1]]);
            Process::assertRan(fn ($process) => str_ends_with($process->command[1], 'generate-pdf-cover.py'));
        } finally {
            if ($cover && is_file($cover)) {
                unlink($cover);
            }
        }
    }
    public function test_legacy_import_generates_a_cover_when_no_cover_was_uploaded(): void
    {
        $source = storage_path('testing/cover-import-'.uniqid());
        $uploads = $source.DIRECTORY_SEPARATOR.'uploads';
        mkdir($uploads, 0755, true);
        $pdf = "%PDF-1.4\nCover generation fixture\n%%EOF";
        file_put_contents($uploads.DIRECTORY_SEPARATOR.'test.pdf', $pdf);
        $manifest = [
            'format' => 1, 'label' => 'Cover generation test '.uniqid(), 'source_database' => 'legacy_test',
            'categories' => [['id' => 21, 'name' => 'Test Magazines', 'slug' => 'test-magazines']],
            'books' => [[
                'id' => 812, 'title' => 'Imported Test Magazine', 'author' => 'CU', 'description' => 'Test',
                'category_id' => 21, 'file_path' => 'uploads/test.pdf', 'cover_image' => null, 'upload_date' => null,
            ]],
            'files' => [[
                'path' => 'uploads/test.pdf', 'sha256' => hash('sha256', $pdf), 'bytes' => strlen($pdf),
                'pdf' => ['page_count' => 1, 'encrypted' => false, 'validation_status' => 'valid'],
            ]],
        ];
        $manifestPath = $source.DIRECTORY_SEPARATOR.'manifest.json';
        $manifestContents = json_encode($manifest, JSON_THROW_ON_ERROR);
        file_put_contents($manifestPath, $manifestContents);
        $book = null;
        $cover = null;
        $imported = config('library.root').DIRECTORY_SEPARATOR.'legacy'.DIRECTORY_SEPARATOR.hash('sha256', $manifestContents);

        try {
            Process::fake(function ($process) {
                $image = imagecreatetruecolor(600, 800);
                imagejpeg($image, $process->command[3], 88);
                imagedestroy($image);
                return new FakeProcessResult;
            });

            $this->artisan('library:import-legacy', [
                'manifest' => $manifestPath, '--source' => $source, '--apply' => true,
            ])->assertExitCode(0);

            $book = LibraryBook::where('legacy_id', 812)->firstOrFail();
            $this->assertSame('covers/book-'.$book->id.'.jpg', $book->cover_path);
            $cover = config('library.root').DIRECTORY_SEPARATOR.$book->cover_path;
            $this->assertFileExists($cover);
            Process::assertRan(fn ($process) => str_ends_with($process->command[1], 'generate-pdf-cover.py'));
        } finally {
            File::deleteDirectory($source);
            File::deleteDirectory($imported);
            if ($cover && is_file($cover)) {
                unlink($cover);
            }
        }
    }
    public function test_existing_registration_login_profile_and_logout_still_work(): void
    {
        $response = $this->postJson('/api/members/register', ['full_name'=>'New Test', 'email'=>'new@example.test', 'password'=>'test-password', 'password_confirmation'=>'test-password'])->assertCreated();
        $token = $response->json('token'); $id = $response->json('member.id');
        $this->getJson('/api/members/'.$id.'/profile', $this->auth($token))->assertOk();
        $this->postJson('/api/members/logout', [], $this->auth($token))->assertOk();
        $this->getJson('/api/library/me/books', $this->auth($token))->assertUnauthorized();
        $this->postJson('/api/members/login', ['email'=>'new@example.test','password'=>'test-password'])->assertOk()->assertJsonStructure(['token','member']);
    }
    public function test_rate_limit_and_maintenance_switch(): void
    {
        for ($i=0; $i<10; $i++) $this->postJson('/api/members/login', ['email'=>'wrong@example.test','password'=>'wrong'])->assertUnprocessable();
        $this->postJson('/api/members/login', ['email'=>'wrong@example.test','password'=>'wrong'])->assertStatus(429);
        config(['library.enabled'=>false]); $this->getJson('/api/library/books')->assertStatus(503);
    }
}
