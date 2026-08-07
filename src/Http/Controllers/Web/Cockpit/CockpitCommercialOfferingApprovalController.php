<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialOffering;
use LBHurtado\XChange\Models\CommercialOffering;

final class CockpitCommercialOfferingApprovalController extends Controller
{
    public function __construct(
        private readonly ManageCommercialOffering $manage,
    ) {}

    public function store(Request $request, CommercialOffering $offering): RedirectResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);

        $validated = $request->validate([
            'authorization_reference' => ['required', 'string', 'max:190'],
        ]);

        $this->manage->publish(
            $operator,
            $offering,
            (string) $validated['authorization_reference'],
        );

        return back()->with('success', 'Commercial Offering published.');
    }
}
