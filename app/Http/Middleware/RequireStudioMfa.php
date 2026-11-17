<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireStudioMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->canViewStudio() && $user->studio_company_id, 403);
        if ((int) $request->session()->get('studio_mfa_verified_user_id', 0) !== (int) $user->id ||
            !$user->studio_mfa_enabled_at || !$user->studio_mfa_secret ||
            !hash_equals(hash('sha256', $user->studio_mfa_secret), (string) $request->session()->get('studio_mfa_enrollment_fingerprint',''))) {
            if ($request->expectsJson()) {
                abort(403, 'Multi-factor authentication required.');
            }
            return redirect()->route($user->studio_mfa_enabled_at ? 'studio.mfa.challenge' : 'studio.mfa.setup');
        }
        return $next($request);
    }
}
