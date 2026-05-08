<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

class PayCodeShowPageController extends Controller
{
    public function __invoke(string $code, VoucherLifecycleServiceContract $vouchers): Response
    {
        $voucher = $vouchers->showByCode($code);

        return Inertia::render('x-change/pay-codes/Show', [
            'voucher' => $voucher,
        ]);
    }
}
