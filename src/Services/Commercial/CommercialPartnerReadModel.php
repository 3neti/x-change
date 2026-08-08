<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

final class CommercialPartnerReadModel
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $earned = CommercialAllocation::query()
            ->selectRaw('commercial_partner_id, currency, SUM(amount_minor) as amount_minor')
            ->where('category', 'partner_commission')
            ->where('status', 'posted')
            ->whereNotNull('commercial_partner_id')
            ->groupBy('commercial_partner_id', 'currency')
            ->get()
            ->groupBy('commercial_partner_id');
        $reserved = PartnerCommissionPayoutBatch::query()
            ->selectRaw('commercial_partner_id, currency, SUM(amount_minor) as amount_minor')
            ->whereNotNull('commercial_partner_id')
            ->whereIn('status', ['awaiting_approval', 'approved', 'submitted', 'pending', 'suspense'])
            ->groupBy('commercial_partner_id', 'currency')
            ->get()
            ->groupBy('commercial_partner_id');
        $settled = PartnerCommissionPayoutBatch::query()
            ->selectRaw('commercial_partner_id, currency, SUM(amount_minor) as amount_minor')
            ->whereNotNull('commercial_partner_id')
            ->where('status', 'settled')
            ->groupBy('commercial_partner_id', 'currency')
            ->get()
            ->groupBy('commercial_partner_id');

        return [
            'schema' => 'x-change.cockpit.commercial-partners.v1',
            'summary' => [
                'active_count' => CommercialPartner::query()->active()->count(),
                'awaiting_approval_count' => CommercialPartnerRevision::query()
                    ->where('status', CommercialPartnerRevisionStatus::AwaitingApproval)->count()
                    + CommercialPartnerDestinationRevision::query()
                        ->where('status', CommercialPartnerRevisionStatus::AwaitingApproval)->count(),
                'legacy_unresolved_count' => CommercialAllocation::query()
                    ->where('category', 'partner_commission')
                    ->whereNull('commercial_partner_id')
                    ->whereNotNull('legacy_partner_reference')
                    ->count(),
                'legacy_unresolved_minor' => (int) CommercialAllocation::query()
                    ->where('category', 'partner_commission')
                    ->whereNull('commercial_partner_id')
                    ->whereNotNull('legacy_partner_reference')
                    ->sum('amount_minor'),
            ],
            'partners' => CommercialPartner::query()
                ->with(['revisions', 'destinationRevisions'])
                ->latest('updated_at')
                ->get()
                ->map(function (CommercialPartner $partner) use ($earned, $reserved, $settled): array {
                    $revision = $partner->revisions
                        ->firstWhere('status', CommercialPartnerRevisionStatus::Approved);
                    $destinations = $partner->destinationRevisions
                        ->where('status', CommercialPartnerRevisionStatus::Approved)
                        ->values();

                    return [
                        'id' => $partner->getKey(),
                        'reference' => $partner->reference,
                        'display_name' => $partner->display_name,
                        'status' => $partner->status->value,
                        'revision' => $revision ? [
                            'id' => $revision->getKey(),
                            'version' => $revision->version,
                            'legal_name' => $revision->legal_name,
                            'attribution_basis' => $revision->attribution_basis,
                            'authorization_reference' => $revision->authorization_reference,
                            'effective_at' => $revision->effective_at?->toIso8601String(),
                        ] : null,
                        'destinations' => $destinations->map(fn (CommercialPartnerDestinationRevision $destination): array => [
                            'id' => $destination->getKey(),
                            'version' => $destination->version,
                            'provider' => $destination->provider,
                            'connection_reference' => $destination->connection_reference,
                            'currency' => $destination->currency,
                            'summary' => $destination->destination_summary,
                            'effective_at' => $destination->effective_at?->toIso8601String(),
                        ])->all(),
                        'balances' => $this->balances(
                            $earned->get($partner->getKey(), collect()),
                            $reserved->get($partner->getKey(), collect()),
                            $settled->get($partner->getKey(), collect()),
                        ),
                    ];
                })->all(),
            'pending_revisions' => CommercialPartnerRevision::query()
                ->with('partner')
                ->where('status', CommercialPartnerRevisionStatus::AwaitingApproval)
                ->latest('submitted_at')
                ->get()
                ->map(fn (CommercialPartnerRevision $revision): array => [
                    'id' => $revision->getKey(),
                    'partner_reference' => $revision->partner->reference,
                    'display_name' => $revision->display_name,
                    'version' => $revision->version,
                    'attribution_basis' => $revision->attribution_basis,
                    'authorization_reference' => $revision->authorization_reference,
                    'submitted_at' => $revision->submitted_at?->toIso8601String(),
                ])->all(),
            'pending_destinations' => CommercialPartnerDestinationRevision::query()
                ->with('partner')
                ->where('status', CommercialPartnerRevisionStatus::AwaitingApproval)
                ->latest('submitted_at')
                ->get()
                ->map(fn (CommercialPartnerDestinationRevision $destination): array => [
                    'id' => $destination->getKey(),
                    'partner_reference' => $destination->partner->reference,
                    'partner_name' => $destination->partner->display_name,
                    'provider' => $destination->provider,
                    'connection_reference' => $destination->connection_reference,
                    'currency' => $destination->currency,
                    'summary' => $destination->destination_summary,
                    'authorization_reference' => $destination->authorization_reference,
                    'submitted_at' => $destination->submitted_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /** @return list<array{currency:string, earned_minor:int, reserved_minor:int, settled_minor:int, available_minor:int}> */
    private function balances(mixed $earned, mixed $reserved, mixed $settled): array
    {
        $currencies = collect($earned)->pluck('currency')
            ->merge(collect($reserved)->pluck('currency'))
            ->merge(collect($settled)->pluck('currency'))
            ->unique()->sort()->values();

        return $currencies->map(function (string $currency) use ($earned, $reserved, $settled): array {
            $earnedMinor = (int) collect($earned)->firstWhere('currency', $currency)?->amount_minor;
            $reservedMinor = (int) collect($reserved)->firstWhere('currency', $currency)?->amount_minor;
            $settledMinor = (int) collect($settled)->firstWhere('currency', $currency)?->amount_minor;

            return [
                'currency' => $currency,
                'earned_minor' => $earnedMinor,
                'reserved_minor' => $reservedMinor,
                'settled_minor' => $settledMinor,
                'available_minor' => max(0, $earnedMinor - $reservedMinor - $settledMinor),
            ];
        })->all();
    }
}
