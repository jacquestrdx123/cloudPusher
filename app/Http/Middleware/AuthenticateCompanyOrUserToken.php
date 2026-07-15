<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\UserApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompanyOrUserToken
{
    /**
     * Accept either a company HMAC bearer token or a user API token.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if (! $company instanceof Company || ! $company->is_active) {
            abort(404);
        }

        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            abort(401, 'Unauthenticated.');
        }

        if ($this->authenticateUserToken($request, $company, $token)) {
            return $next($request);
        }

        if (hash_equals($company->hmac_secret, $token)) {
            $request->attributes->set('auth_via', 'company');

            return $next($request);
        }

        abort(401, 'Unauthenticated.');
    }

    private function authenticateUserToken(Request $request, Company $company, string $token): bool
    {
        $apiToken = UserApiToken::query()
            ->where('token_hash', UserApiToken::hashToken($token))
            ->with('user')
            ->first();

        if ($apiToken === null || $apiToken->isExpired() || $apiToken->user === null) {
            return false;
        }

        if ((int) $apiToken->user->company_id !== (int) $company->id) {
            return false;
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('auth_via', 'user');
        $request->attributes->set('api_token', $apiToken);
        $request->setUserResolver(fn () => $apiToken->user);

        return true;
    }
}
