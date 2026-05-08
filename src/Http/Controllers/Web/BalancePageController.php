<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BalancePageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('x-change/balances/Index');
    }
}
