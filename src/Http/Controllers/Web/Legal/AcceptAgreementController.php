<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Legal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Services\Legal\CurrentAgreementService;

final class AcceptAgreementController extends Controller
{
    public function __invoke(Request $request, CurrentAgreementService $agreements): RedirectResponse
    {
        abort_unless($agreements->enabled(), 404);

        $document = $agreements->document();
        $validated = $request->validate([
            'accepted' => ['required', 'accepted'],
            'agreement_sha256' => ['required', 'string', Rule::in([$document->sha256])],
        ], [
            'agreement_sha256.in' => 'The agreement changed while you were reviewing it. Please review the current version.',
        ]);
        $user = $request->user();

        abort_unless($user instanceof Model, 401);

        $agreements->accept($user, $request, $document);

        return redirect()->intended((string) config(
            'x-change.legal.eula.accepted_redirect',
            route('x-change.cockpit.entry'),
        ));
    }
}
