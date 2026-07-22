<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompanySyncToken
{
    /**
     * Authenticate a directory-sync request for a company. Accepts either the
     * company's own HMAC secret or the platform provisioning key via
     * Authorization: Bearer {token}.
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
            abort(401, 'Invalid company token.');
        }

        if (hash_equals($company->hmac_secret, $token)) {
            return $next($request);
        }

        $provisioningKey = config('pushservice.sync.provisioning_key');

        if (is_string($provisioningKey) && $provisioningKey !== '' && hash_equals($provisioningKey, $token)) {
            return $next($request);
        }

        abort(401, 'Invalid company token.');
    }
}
