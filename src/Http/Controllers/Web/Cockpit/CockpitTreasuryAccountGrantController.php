<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Treasury\RequestTreasuryAccountGrant;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreTreasuryAccountGrantRequest;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleUserModelResolver;

final class CockpitTreasuryAccountGrantController extends Controller
{
    public function store(
        StoreTreasuryAccountGrantRequest $request,
        LifecycleUserModelResolver $users,
        RequestTreasuryAccountGrant $grants,
    ): RedirectResponse {
        $validated = $request->validated();
        $model = $users->resolve();
        $recipient = $model::query()->findOrFail($validated['recipient_id']);
        $maker = $request->user();
        abort_unless($recipient instanceof Model && $maker instanceof Model, 403);

        $grants->handle(
            recipient: $recipient,
            amountMinor: (int) $validated['amount_minor'],
            currency: (string) $validated['currency'],
            connectionReference: (string) $validated['connection_reference'],
            purpose: (string) $validated['purpose'],
            idempotencyReference: (string) $validated['idempotency_reference'],
            maker: $maker,
            testAllocation: (bool) ($validated['test_allocation'] ?? false),
        );

        return back();
    }
}
