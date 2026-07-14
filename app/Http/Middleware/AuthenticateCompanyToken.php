<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompanyToken
{
    /**
     * Authenticate the company via Authorization: Bearer {company_token},
     * where the token is the company's HMAC secret.
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

        if (! is_string($token) || $token === '' || ! hash_equals($company->hmac_secret, $token)) {
            abort(401, 'Invalid company token.');
        }

        return $next($request);
    }
}
