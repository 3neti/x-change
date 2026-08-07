<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;

final class CockpitCommercialOfferingPageController extends Controller
{
    public function __construct(
        private readonly CommercialOfferingResolverContract $offerings,
        private readonly CommercialOperatorAuthorityContract $authority,
    ) {}

    public function __invoke(Request $request): Response
    {
        $operator = $request->user();

        abort_unless($operator instanceof Model && $this->mayView($operator), 404);

        $profile = $request->string('profile')->value() === 'account_funding'
            ? 'account_funding'
            : 'pay_code';
        $offering = $this->offerings->resolve($profile);

        return Inertia::render('x-change/cockpit/CommercialOfferings', [
            'commercial_offering' => [
                'profile' => $profile,
                'active' => $offering->toArray(),
                'source' => $this->source($profile),
                'can_manage' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ManageOfferings,
                ),
                'can_approve' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ApproveOfferings,
                ),
                'pending' => CommercialOffering::query()
                    ->where('profile', $profile)
                    ->where('status', CommercialOfferingStatus::PendingApproval->value)
                    ->latest('submitted_at')
                    ->get()
                    ->map(fn (CommercialOffering $pending): array => [
                        'id' => $pending->getKey(),
                        'reference' => $pending->reference,
                        'version' => $pending->version,
                        'snapshot_hash' => $pending->snapshot_hash,
                        'effective_at' => $pending->effective_at?->toIso8601String(),
                        'submitted_at' => $pending->submitted_at?->toIso8601String(),
                        'maker' => [
                            'type' => $pending->created_by_type,
                            'id' => $pending->created_by_id,
                        ],
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function mayView(Model $operator): bool
    {
        foreach (CommercialOperatorCapability::cases() as $capability) {
            if ($this->authority->allows($operator, $capability)) {
                return true;
            }
        }

        return false;
    }

    private function source(string $profile): string
    {
        $published = (bool) config('x-change.commercial.offerings.use_published', false)
            && CommercialOffering::query()
                ->where('profile', $profile)
                ->where('status', CommercialOfferingStatus::Published->value)
                ->where('effective_at', '<=', now())
                ->exists();

        return $published ? 'published' : 'package_default';
    }
}
