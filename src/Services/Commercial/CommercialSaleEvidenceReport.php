<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Models\CommercialSale;

final readonly class CommercialSaleEvidenceReport
{
    public function __construct(
        private CommercialInvoicingAuthorityInspector $invoicingAuthority,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CommercialSale $sale): array
    {
        $sale->loadMissing('allocations');
        $allocations = $sale->allocations->sortBy('sequence')->values();
        $allocationTotalMinor = (int) $allocations->sum('amount_minor');
        $taxMinor = (int) $allocations
            ->whereIn('category', ['tax', 'tax_payable'])
            ->sum('amount_minor');
        $forbiddenPrincipalCategories = [
            'principal',
            'client_funds',
            'provider_inventory',
            'settlement_balance',
            'float',
            'pass_through_money',
        ];
        $principalSeparated = $allocationTotalMinor === $sale->total_price_minor
            && $allocations->whereIn('category', $forbiddenPrincipalCategories)->isEmpty();
        $snapshotHashMatches = hash_equals(
            (string) $sale->snapshot_hash,
            hash('sha256', json_encode(
                $sale->snapshot,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            )),
        );
        $postedEvidenceComplete = match ($sale->status) {
            'posted' => ($sale->total_price_minor === 0 || filled($sale->charge_operation_reference))
                && $allocations->every(
                    static fn ($allocation): bool => $allocation->status === 'posted'
                        && ($allocation->amount_minor === 0 || filled($allocation->treasury_operation_reference)),
                ),
            'reversed' => $sale->reversed_at !== null
                && ($sale->total_price_minor === 0 || filled($sale->charge_operation_reference))
                && $allocations->every(
                    static fn ($allocation): bool => $allocation->status === 'reversed'
                        && ($allocation->amount_minor === 0 || filled($allocation->treasury_reversal_operation_reference)),
                ),
            default => false,
        };
        $reportVerified = $principalSeparated && $snapshotHashMatches && $postedEvidenceComplete;
        $authority = $this->invoicingAuthority->inspect();

        return [
            'schema' => (string) config(
                'x-change.commercial.receipt_reporting.schema',
                'x-change.commercial-sale-evidence-report.v1',
            ),
            'as_of' => now()->toRfc3339String(),
            'sale' => [
                'reference' => $sale->reference,
                'status' => $sale->status,
                'buyer_reference_hash' => hash('sha256', (string) $sale->buyer_reference),
                'quote_reference' => $sale->quote_reference,
                'catalog_reference' => $sale->catalog_reference,
                'catalog_version' => $sale->catalog_version,
                'accepted_at' => $sale->accepted_at?->toRfc3339String(),
                'posted_at' => $sale->posted_at?->toRfc3339String(),
                'reversed_at' => $sale->reversed_at?->toRfc3339String(),
            ],
            'amounts' => [
                'currency' => $sale->currency,
                'principal_minor' => 0,
                'commercial_charge_minor' => $sale->total_price_minor,
                'tax_minor' => $taxMinor,
                'non_tax_charge_minor' => $sale->total_price_minor - $taxMinor,
                'allocation_total_minor' => $allocationTotalMinor,
                'principal_reported_separately' => $principalSeparated,
            ],
            'allocations' => $allocations->map(static fn ($allocation): array => [
                'sequence' => $allocation->sequence,
                'category' => $allocation->category,
                'amount_minor' => $allocation->amount_minor,
                'currency' => $allocation->currency,
                'status' => $allocation->status,
                'treasury_operation_reference' => $allocation->treasury_operation_reference,
            ])->all(),
            'confirmation' => [
                'document_kind' => 'commercial_charge_confirmation',
                'reference' => $sale->reference,
                'verified' => $reportVerified,
                'not_a_tax_invoice' => true,
            ],
            'invoice' => [
                'authority_ready' => $authority['ready'],
                'document_type' => $authority['document_type'],
                'status' => $authority['ready']
                    ? 'authority_ready_invoice_not_issued'
                    : 'withheld_authority_unresolved',
                'invoice_reference' => null,
            ],
            'controls' => [
                'snapshot_hash_matches' => $snapshotHashMatches,
                'allocations_reconcile' => $allocationTotalMinor === $sale->total_price_minor,
                'posted_evidence_complete' => $postedEvidenceComplete,
                'principal_separated' => $principalSeparated,
                'report_verified' => $reportVerified,
            ],
        ];
    }
}
