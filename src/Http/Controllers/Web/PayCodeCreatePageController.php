<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

class PayCodeCreatePageController extends Controller
{
    public function __invoke(VoucherLifecycleServiceContract $vouchers): Response
    {
        return Inertia::render('x-change/pay-codes/Create');
    }
}
