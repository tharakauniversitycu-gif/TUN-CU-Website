<?php
namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;

class LibraryAccess
{
    public function handle(Request $request, Closure $next, string $mode = 'public')
    {
        abort_unless(config('library.enabled'), 503, 'The library is temporarily unavailable.');
        if ($mode === 'member') {
            abort_unless($request->user() instanceof Member, 403, 'A member account is required.');
            abort_unless(in_array($request->user()->status, config('library.eligible_statuses'), true), 403,
                'Library access is unavailable for your account status. Please contact CU leadership.');
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($mode === 'member') $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
