<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeEngineeringPreview;

final class CockpitPayCodeEngineeringPreviewController extends Controller
{
    public function __construct(
        private readonly VoucherAccessContract $vouchers,
        private readonly CockpitPayCodeDetailAccess $access,
        private readonly CockpitPayCodeEngineeringPreview $preview,
    ) {}

    public function __invoke(Request $request, string $code): JsonResponse
    {
        $voucher = $this->vouchers->findByCodeOrFail($code);
        $actor = $request->user();

        abort_unless(
            $actor !== null && $this->access->canView($actor, $voucher),
            404,
        );

        return response()
            ->json($this->preview->forCode($voucher->code))
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
    }
}
