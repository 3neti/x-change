<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\StoredValue\HolderStoredValueReadModel;

final class StoredValueInstrumentPageController extends Controller
{
    public function __invoke(
        Request $request,
        string $instrument,
        HolderStoredValueReadModel $storedValue,
    ): Response {
        $holder = $request->user();
        abort_unless($holder instanceof Model, 404);

        return Inertia::render('x-change/balances/StoredValueShow', [
            'instrument' => $storedValue->detail(
                holder: $holder,
                reference: $instrument,
                page: max(1, $request->integer('page', 1)),
            ),
        ]);
    }
}
