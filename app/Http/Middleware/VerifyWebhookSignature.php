<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Verify the HMAC-SHA256 signature of an inbound webhook against the
     * resolved company's secret before allowing the request through.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if (! $company instanceof Company || ! $company->is_active) {
            abort(404);
        }

        $header = (string) $request->header((string) config('pushservice.signature_header'));
        $provided = str_contains($header, '=') ? explode('=', $header, 2)[1] : $header;

        $expected = hash_hmac('sha256', $request->getContent(), $company->hmac_secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
