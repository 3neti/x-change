<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\BuildBalanceOverview;

class BalancePageController extends Controller
{
    public function __invoke(Request $request, BuildBalanceOverview $balances): Response
    {
        return Inertia::render('x-change/balances/Index', [
            'balance_overview' => $balances->handle($request->user()),
        ]);
    }
}
