<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareCardRendererContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;

final class ClaimShareCardController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        VoucherFlowCapabilityResolverContract $capabilities,
        ClaimShareCardRendererContract $renderer,
        ?string $sha256 = null,
    ): Response {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->first();

        abort_unless(
            $voucher instanceof Voucher
                && $capabilities->resolve($voucher)->can_disburse,
            404,
        );

        $card = $renderer->render(
            $voucher,
            route('x-change.claim.show', ['code' => $voucher->code]),
        );

        if ($sha256 !== null) {
            abort_unless(
                $card->immutable
                    && hash_equals(
                        strtolower($sha256),
                        trim($card->etag, '"'),
                    ),
                404,
            );
        }

        $headers = $this->headers($card->etag, $card->immutable);

        if ($request->header('If-None-Match') === $card->etag) {
            return response('', 304, $headers);
        }

        return response($card->contents, 200, $headers);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $etag, bool $immutable): array
    {
        $maxAge = $immutable
            ? max(
                86400,
                (int) config(
                    'x-change.claim.share.artifact.cache_ttl_seconds',
                    31536000,
                ),
            )
            : max(
                60,
                (int) config('x-change.claim.share.cache_ttl_seconds', 300),
            );
        $cacheControl = "public, max-age={$maxAge}, s-maxage={$maxAge}";

        if ($immutable) {
            $cacheControl .= ', immutable';
        }

        return [
            'Content-Type' => 'image/png',
            'Cache-Control' => $cacheControl,
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
