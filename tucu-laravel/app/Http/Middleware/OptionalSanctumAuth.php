<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * OptionalSanctumAuth
 * ---------------------------------------------------------------
 * Used on public endpoints (prayer requests, event registration,
 * newsletter subscribe) that should work for BOTH:
 *
 *  - Guests / non-members (no Authorization header)
 *  - Logged-in members (valid Sanctum bearer token)
 *
 * If a valid token IS present, the member is resolved and attached
 * to the request via $request->user('sanctum'), so the controller
 * can automatically link the submission to that member's portal.
 *
 * If the token is missing or invalid, the request simply proceeds
 * as a guest — it is never rejected here.
 * ---------------------------------------------------------------
 */
class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token) {
            // Attempt to resolve the member from the Sanctum token.
            // Any failure is silently ignored — the request continues as a guest.
            try {
                $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

                if ($accessToken && $accessToken->tokenable_type === \App\Models\Member::class) {
                    $member = $accessToken->tokenable;

                    if ($member) {
                        Auth::shouldUse('sanctum');
                        Auth::guard('sanctum')->setUser($member);
                        $request->setUserResolver(fn () => $member);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore — proceed as guest
            }
        }

        return $next($request);
    }
}
