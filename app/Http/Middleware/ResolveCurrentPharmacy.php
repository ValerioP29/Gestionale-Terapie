<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentPharmacy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentPharmacy
{
    public function __construct(private readonly CurrentPharmacy $currentPharmacy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentPharmacy->resolveFromRequest($request);

        return $next($request);
    }
}
