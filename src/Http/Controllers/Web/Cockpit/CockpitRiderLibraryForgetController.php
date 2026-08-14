<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Cockpit\ForgetRiderLibraryEntry;
use LBHurtado\XChange\Http\Requests\Cockpit\ForgetRiderLibraryEntryRequest;
use LBHurtado\XChange\Models\RiderLibraryEntry;

class CockpitRiderLibraryForgetController extends Controller
{
    public function __invoke(
        ForgetRiderLibraryEntryRequest $request,
        RiderLibraryEntry $riderLibraryEntry,
        ForgetRiderLibraryEntry $forget,
    ): RedirectResponse {
        $owner = $request->user();

        abort_unless($owner instanceof Model, 403);

        $forget->handle($owner, $riderLibraryEntry);

        return back()->with('success', 'Rider forgotten.');
    }
}
