<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CockpitQuickGeneratePageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('x-change/cockpit/QuickGenerate');
    }
}
