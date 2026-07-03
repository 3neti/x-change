<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CockpitVoucherDetailPageController extends Controller
{
    public function __invoke(string $code): Response
    {
        return Inertia::render('x-change/cockpit/VoucherDetail', [
            'code' => $code,
        ]);
    }
}
