<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantScopes
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            DeviceToken::addGlobalScope(
                'tenant',
                fn (Builder $query) => $query->whereHas(
                    'user',
                    fn (Builder $query) => $query->where('company_id', $tenant->getKey()),
                ),
            );
        }

        return $next($request);
    }
}
