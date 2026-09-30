<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthorizePublicIssuanceOrderAccess
{
    public function __construct(
        private PublicIssuanceOrderAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        abort_unless($order instanceof PayCodeIssuanceFundingOrder, 404);
        $this->access->authorize($order, $request);

        return $next($request);
    }
}
