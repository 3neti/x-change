<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

final class DashboardPageController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('x-change.cockpit.entry');
    }
}
