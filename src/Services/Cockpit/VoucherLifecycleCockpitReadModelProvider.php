<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardActivityData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardPipelineStageData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardRiskSignalData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelBundleData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Data\Cockpit\CockpitVoucherReadModelData;
use LBHurtado\XChange\Exceptions\VoucherNotFound;

class VoucherLifecycleCockpitReadModelProvider implements CockpitReadModelProviderContract
{
    public function __construct(
        private readonly VoucherLifecycleServiceContract $vouchers,
        private readonly NullCockpitReadModelProvider $fallback = new NullCockpitReadModelProvider,
    ) {}

    public function forVoucher(CockpitReadModelQueryData $query): CockpitReadModelBundleData
    {
        $code = $this->normalizeCode($query->code);

        if ($code === null) {
            return $this->fallback->forVoucher($query);
        }

        try {
            $detail = $this->toArray($this->vouchers->showByCode($code));
        } catch (VoucherNotFound) {
            return $this->fallback->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                operatorId: $query->operatorId,
                include: $query->include,
                correlationId: $query->correlationId,
            ));
        }

        if ($detail === []) {
            return $this->fallback->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                operatorId: $query->operatorId,
                include: $query->include,
                correlationId: $query->correlationId,
            ));
        }

        $fallback = $this->fallback->forVoucher(new CockpitReadModelQueryData(
            code: $code,
            operatorId: $query->operatorId,
            include: $query->include,
            correlationId: $query->correlationId,
        ));

        return new CockpitReadModelBundleData(
            code: $this->summaryCode($detail, $code),
            voucher: new CockpitVoucherReadModelData(
                code: $this->summaryCode($detail, $code),
                status: $this->summaryStatus($detail),
                summary: $this->summary($detail, $code),
                redactions: [
                    'payloads' => 'sanitized-summary-only',
                    'excluded' => [
                        'id',
                        'voucher_id',
                        'issuer_id',
                        'instructions',
                        'claims',
                        'approval',
                        'provider_payload',
                        'raw_payload',
                        'wallet',
                        'provider',
                    ],
                ],
                authorized: true,
            ),
            execution: $fallback->execution,
            journal: $fallback->journal,
            actions: $fallback->actions,
            feedback: $fallback->feedback,
        );
    }

    public function forDashboard(CockpitReadModelQueryData $query): CockpitDashboardReadModelData
    {
        $rows = collect($this->vouchers->list())
            ->map(fn (mixed $row): array => $this->toArray($row))
            ->filter(fn (array $row): bool => $this->summaryCode($row, '') !== '')
            ->values();

        $issued = $this->countStatus($rows, 'issued');
        $redeemed = $this->countStatus($rows, 'redeemed');
        $expired = $this->countStatus($rows, 'expired');
        $awaitingApproval = $rows
            ->filter(fn (array $row): bool => $this->stringValue($row['display_status'] ?? null, '') === 'awaiting_approval')
            ->count();
        $attention = $expired + $awaitingApproval;

        return new CockpitDashboardReadModelData(
            status: 'available',
            authorized: true,
            metrics: [
                new CockpitDashboardMetricData(
                    key: 'pay-codes-visible',
                    label: 'Pay Codes Visible',
                    value: (string) $rows->count(),
                    helper: 'Sanitized voucher lifecycle list rows',
                ),
                new CockpitDashboardMetricData(
                    key: 'issued-pay-codes',
                    label: 'Issued',
                    value: (string) $issued,
                    helper: 'Read-only lifecycle summary',
                    tone: 'healthy',
                ),
                new CockpitDashboardMetricData(
                    key: 'redeemed-pay-codes',
                    label: 'Redeemed',
                    value: (string) $redeemed,
                    helper: 'Includes awaiting approval display states',
                    tone: 'healthy',
                ),
                new CockpitDashboardMetricData(
                    key: 'attention-pay-codes',
                    label: 'Needs Attention',
                    value: (string) $attention,
                    helper: 'Expired or awaiting approval summaries only',
                    tone: 'warning',
                ),
            ],
            pipeline: [
                new CockpitDashboardPipelineStageData(
                    key: 'issued',
                    label: 'Issued',
                    value: (string) $issued,
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'redeemed',
                    label: 'Redeemed',
                    value: (string) $redeemed,
                    tone: 'healthy',
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'expired',
                    label: 'Expired',
                    value: (string) $expired,
                    tone: 'warning',
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'awaiting-approval',
                    label: 'Awaiting Approval',
                    value: (string) $awaitingApproval,
                    tone: 'warning',
                ),
            ],
            risk_signals: [
                new CockpitDashboardRiskSignalData(
                    key: 'expired-pay-codes',
                    label: 'Expired Pay Codes',
                    value: $expired.' sanitized summaries',
                    severity: 'warning',
                ),
                new CockpitDashboardRiskSignalData(
                    key: 'awaiting-approval',
                    label: 'Awaiting Approval',
                    value: $awaitingApproval.' sanitized summaries',
                    severity: 'watch',
                ),
            ],
            activity: $this->dashboardActivity($rows),
            redactions: [
                'payloads' => 'sanitized-dashboard-summary-only',
                'excluded' => $this->excludedPayloadKeys(),
            ],
        );
    }

    public function forPayCodeList(CockpitReadModelQueryData $query): CockpitPayCodeListReadModelData
    {
        $rows = collect($this->vouchers->list())
            ->map(fn (mixed $row): ?CockpitPayCodeListRecordData => $this->listRecord($this->toArray($row)))
            ->filter()
            ->values()
            ->all();

        return new CockpitPayCodeListReadModelData(
            status: 'available',
            authorized: true,
            query: $query->code,
            records: $rows,
            redactions: [
                'payloads' => 'sanitized-list-summary-only',
                'excluded' => $this->excludedPayloadKeys(),
            ],
        );
    }

    private function normalizeCode(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $detail): array
    {
        if (is_array($detail)) {
            return $detail;
        }

        if ($detail instanceof Arrayable) {
            return $detail->toArray();
        }

        if ($detail instanceof JsonSerializable) {
            $serialized = $detail->jsonSerialize();

            return is_array($serialized) ? $serialized : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function summary(array $detail, string $fallbackCode): array
    {
        return collect([
            'code' => $this->summaryCode($detail, $fallbackCode),
            'status' => $this->summaryStatus($detail),
            'display_status' => $detail['display_status'] ?? null,
            'amount' => $detail['amount'] ?? null,
            'currency' => $detail['currency'] ?? null,
            'claimed' => $detail['claimed'] ?? null,
            'fully_claimed' => $detail['fully_claimed'] ?? null,
            'created_at' => $detail['created_at'] ?? null,
            'starts_at' => $detail['starts_at'] ?? null,
            'expires_at' => $detail['expires_at'] ?? null,
            'redeemed_at' => $detail['redeemed_at'] ?? null,
        ])
            ->filter(fn (mixed $value, string $key): bool => $key === 'redeemed_at' || $value !== null)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function listRecord(array $row): ?CockpitPayCodeListRecordData
    {
        $code = $this->summaryCode($row, '');

        if ($code === '') {
            return null;
        }

        $status = $this->summaryStatus($row);

        return new CockpitPayCodeListRecordData(
            code: $code,
            template: $this->stringValue($row['template'] ?? null, 'Pay Code'),
            amount: $this->amountValue($row['amount'] ?? null),
            currency: $this->nullableString($row['currency'] ?? null),
            status: $status,
            display_status: $this->stringValue($row['display_status'] ?? null, $status),
            owner: $this->stringValue($row['owner'] ?? null, 'Redacted'),
            last_activity: $this->nullableString(
                $row['last_activity']
                    ?? $row['updated_at']
                    ?? $row['redeemed_at']
                    ?? $row['created_at']
                    ?? null
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function summaryCode(array $detail, string $fallbackCode): string
    {
        $code = $detail['code'] ?? null;

        if (is_scalar($code) && trim((string) $code) !== '') {
            return strtoupper(trim((string) $code));
        }

        return $fallbackCode;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function summaryStatus(array $detail): string
    {
        $status = $detail['status'] ?? null;

        if (is_scalar($status) && trim((string) $status) !== '') {
            return trim((string) $status);
        }

        return 'available';
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return null;
    }

    private function amountValue(mixed $value): string|int|float|null
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function countStatus($rows, string $status): int
    {
        return $rows
            ->filter(fn (array $row): bool => $this->summaryStatus($row) === $status)
            ->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array<int, CockpitDashboardActivityData>
     */
    private function dashboardActivity($rows): array
    {
        return $rows
            ->map(fn (array $row): array => [
                'code' => $this->summaryCode($row, ''),
                'display_status' => $this->stringValue($row['display_status'] ?? null, $this->summaryStatus($row)),
                'timestamp' => $this->nullableString(
                    $row['updated_at']
                        ?? $row['redeemed_at']
                        ?? $row['expires_at']
                        ?? $row['created_at']
                        ?? null
                ),
            ])
            ->filter(fn (array $row): bool => $row['code'] !== '' && $row['timestamp'] !== null)
            ->sortByDesc('timestamp')
            ->take(3)
            ->map(fn (array $row): CockpitDashboardActivityData => new CockpitDashboardActivityData(
                id: $row['code'],
                label: $row['code'],
                description: 'Status: '.$row['display_status'],
                timestamp: $row['timestamp'],
                source: 'system',
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function excludedPayloadKeys(): array
    {
        return [
            'id',
            'voucher_id',
            'issuer_id',
            'instructions',
            'claims',
            'approval',
            'provider_payload',
            'raw_payload',
            'wallet',
            'provider',
        ];
    }
}
