<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Legal;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\Legal\CurrentAgreementService;

final class AgreementPageController extends Controller
{
    public function __invoke(CurrentAgreementService $agreements): View
    {
        abort_unless($agreements->enabled(), 404);

        return view('x-change::legal.eula', [
            'agreement' => $agreements->document(),
        ]);
    }
}
