<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LBHurtado\XChange\Contracts\CommercialPrincipalResolverContract;
use Symfony\Component\HttpFoundation\Response;

final readonly class UseCommercialPrincipalForPublicIssuance
{
    public function __construct(
        private CommercialPrincipalResolverContract $principals,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('x-change.public_auto_generate.enabled', true);

        if (! $enabled && $request->route()?->getName() === 'x-change.public-auto-generate.store') {
            abort(404);
        }

        $principal = $this->principals->resolve();
        $request->setUserResolver(static fn () => $principal);
        $request->attributes->set('x_change_public_auto_generate', true);

        return $next($request);
    }
}
