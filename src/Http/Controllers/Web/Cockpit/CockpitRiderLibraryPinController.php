<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Cockpit\UpdateRiderLibraryEntryPin;
use LBHurtado\XChange\Http\Requests\Cockpit\UpdateRiderLibraryEntryPinRequest;
use LBHurtado\XChange\Models\RiderLibraryEntry;

class CockpitRiderLibraryPinController extends Controller
{
    public function __invoke(
        UpdateRiderLibraryEntryPinRequest $request,
        RiderLibraryEntry $riderLibraryEntry,
        UpdateRiderLibraryEntryPin $update,
    ): RedirectResponse {
        $owner = $request->user();

        abort_unless($owner instanceof Model, 403);

        $update->handle(
            $owner,
            $riderLibraryEntry,
            (bool) $request->validated('pinned'),
        );

        return back()->with('success', 'Rider Library updated.');
    }
}
