<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProvisioningToken
{
    /**
     * Authenticate an upstream provisioning system via
     * Authorization: Bearer {provisioning_key}.
     *
     * When no provisioning key is configured the feature is disabled and the
     * endpoint responds with 404 so its existence is not advertised.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('pushservice.sync.provisioning_key');

        if (! is_string($configured) || $configured === '') {
            abort(404);
        }

        $token = $request->bearerToken();

        if (! is_string($token) || $token === '' || ! hash_equals($configured, $token)) {
            abort(401, 'Invalid provisioning token.');
        }

        return $next($request);
    }
}
