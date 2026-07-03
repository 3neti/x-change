<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
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
