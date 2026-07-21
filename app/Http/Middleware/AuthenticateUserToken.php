<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\UserApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateUserToken
{
    /**
     * Authenticate an end-user via a personal API bearer token.
     *
     * When a `{company}` route parameter is present, the user must belong to that
     * company. Otherwise the user must belong to at least one active company.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            abort(401, 'Unauthenticated.');
        }

        $apiToken = UserApiToken::query()
            ->where('token_hash', UserApiToken::hashToken($token))
            ->with(['user.companies' => fn ($query) => $query->where('is_active', true)])
            ->first();

        if ($apiToken === null || $apiToken->isExpired() || $apiToken->user === null) {
            abort(401, 'Unauthenticated.');
        }

        $user = $apiToken->user;
        $routeCompany = $request->route('company');

        if ($routeCompany instanceof Company) {
            if (! $routeCompany->is_active || ! $user->belongsToCompany($routeCompany)) {
                abort(401, 'Unauthenticated.');
            }
        } elseif ($user->companies->isEmpty()) {
            abort(401, 'Unauthenticated.');
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('auth_via', 'user');
        $request->attributes->set('api_token', $apiToken);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
