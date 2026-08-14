<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Cockpit\SaveRiderLibraryEntry;
use LBHurtado\XChange\Http\Requests\Cockpit\StoreRiderLibraryEntryRequest;

class CockpitRiderLibraryStoreController extends Controller
{
    public function __invoke(
        StoreRiderLibraryEntryRequest $request,
        SaveRiderLibraryEntry $save,
    ): RedirectResponse {
        $owner = $request->user();

        abort_unless($owner instanceof Model, 403);

        $save->handle($owner, $request->validated());

        return back()->with('success', 'Rider saved to your Library.');
    }
}
