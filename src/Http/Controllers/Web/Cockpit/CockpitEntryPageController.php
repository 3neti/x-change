<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\Cockpit\CockpitEntryDestinationResolver;

class CockpitEntryPageController extends Controller
{
    public const NOTICE_SESSION_KEY = 'x-change.cockpit.entry_notice';

    public function __construct(
        private readonly CockpitEntryDestinationResolver $destinations,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $destination = $this->destinations->resolve($request->user());

        return redirect()
            ->route($destination->routeName())
            ->with(self::NOTICE_SESSION_KEY, $destination->notice());
    }
}
