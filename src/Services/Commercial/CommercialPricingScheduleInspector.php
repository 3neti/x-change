<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XCommerce\Data\CommercialCatalogData;
use Throwable;

final class CommercialPricingScheduleInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $schedule = (array) config('x-change.commercial.pricing_schedule', []);
        $catalogPayload = (array) config('x-commerce.catalogs.pay_code', []);

        try {
            $catalog = CommercialCatalogData::fromArray($catalogPayload);
            $catalogSnapshotHash = hash(
                'sha256',
                json_encode($catalog->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );
            $catalogError = null;
        } catch (Throwable $exception) {
            $catalog = null;
            $catalogSnapshotHash = null;
            $catalogError = $exception->getMessage();
        }

        $scheduleApproved = ($schedule['status'] ?? null) === 'approved';
        $catalogMatches = $catalog instanceof CommercialCatalogData
            && $catalog->reference === ($schedule['catalog_reference'] ?? null)
            && $catalog->version === (int) ($schedule['catalog_version'] ?? 0)
            && $catalog->currency === ($schedule['currency'] ?? null)
            && hash_equals(
                (string) ($schedule['catalog_snapshot_hash'] ?? ''),
                (string) $catalogSnapshotHash,
            );
        $principalExcluded = ($schedule['principal_treatment'] ?? null) === 'excluded'
            && $this->waterfallsExcludePrincipal();
        $explicitAuthorization = ($schedule['customer_authorization'] ?? null)
            === 'explicit_quote_acceptance';
        $taxTreatmentResolved = ($schedule['tax_treatment'] ?? null) === 'resolved';
        $customerChargingAuthorized = ($schedule['customer_charging_authorized'] ?? null) === true;
        $scheduleReady = $scheduleApproved
            && $catalogMatches
            && $principalExcluded
            && $explicitAuthorization;
        $customerChargingReady = $scheduleReady
            && $taxTreatmentResolved
            && $customerChargingAuthorized;

        return [
            'schema' => 'x-change.commercial-pricing-schedule-status.v1',
            'reference' => $schedule['reference'] ?? null,
            'version' => $schedule['version'] ?? null,
            'status' => $schedule['status'] ?? null,
            'effective_at' => $schedule['effective_at'] ?? null,
            'currency' => $schedule['currency'] ?? null,
            'approval_reference' => $schedule['approval_reference'] ?? null,
            'schedule_ready' => $scheduleReady,
            'customer_charging_ready' => $customerChargingReady,
            'catalog' => [
                'reference' => $catalog?->reference,
                'version' => $catalog?->version,
                'snapshot_hash' => $catalogSnapshotHash,
                'expected_snapshot_hash' => $schedule['catalog_snapshot_hash'] ?? null,
                'matches_approved_schedule' => $catalogMatches,
                'error' => $catalogError,
            ],
            'principal' => [
                'treatment' => $schedule['principal_treatment'] ?? null,
                'excluded_from_charges_and_revenue' => $principalExcluded,
            ],
            'customer_authorization' => [
                'mode' => $schedule['customer_authorization'] ?? null,
                'explicit' => $explicitAuthorization,
            ],
            'tax' => [
                'treatment' => $schedule['tax_treatment'] ?? null,
                'resolved' => $taxTreatmentResolved,
            ],
            'customer_charging_authorized' => $customerChargingAuthorized,
            'message' => match (true) {
                ! $scheduleReady => 'The approved pricing schedule does not match the executable catalog or principal boundary.',
                $customerChargingReady => 'The approved pricing schedule is ready for customer charging.',
                default => 'Beta Pricing Schedule v1 is approved, but customer charging remains unauthorized until tax treatment is resolved.',
            },
        ];
    }

    private function waterfallsExcludePrincipal(): bool
    {
        $forbiddenCategories = [
            'principal',
            'client_funds',
            'provider_inventory',
            'settlement_balance',
            'float',
            'pass_through_money',
        ];
        $rules = [
            ...(array) config('x-change.commercial.pay_code.waterfall.rules', []),
            ...(array) config('x-change.commercial.pay_code.account_funding_waterfall.rules', []),
        ];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                return false;
            }

            if (in_array((string) ($rule['category'] ?? ''), $forbiddenCategories, true)) {
                return false;
            }
        }

        return true;
    }
}
