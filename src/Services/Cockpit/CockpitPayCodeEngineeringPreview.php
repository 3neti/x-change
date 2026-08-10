<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Arr;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

final readonly class CockpitPayCodeEngineeringPreview
{
    public function __construct(
        private CockpitReadOnlyPageProps $props,
    ) {}

    /** @return array<string, mixed> */
    public function forCode(string $code): array
    {
        $page = $this->props->toVoucherDetailArray($code);
        $voucher = $this->array(data_get($page, 'read_model.voucher'));

        return [
            'schema' => 'x-change.cockpit.pay-code-engineering-preview.v1',
            'pay_code' => [
                'code' => $voucher['code'] ?? $code,
                'status' => $voucher['status'] ?? null,
                'overview' => $this->array($voucher['overview'] ?? null),
            ],
            'instructions' => $this->array($voucher['instructions'] ?? null),
            'claims' => $this->array($voucher['claims'] ?? null),
            'settlement' => $this->array($voucher['settlement'] ?? null),
            'treasury' => $this->array($voucher['treasury'] ?? null),
            'feedback_deliveries' => $this->deliverySummaries(
                $this->list(data_get($page, 'read_model.feedback.deliveries')),
            ),
            'journal_events' => $this->journalSummaries(
                $this->list(data_get($page, 'read_model.journal.entries')),
            ),
            'redactions' => [
                'policy' => 'authorized-sanitized-engineering-preview',
                'binary_evidence' => 'excluded',
                'raw_provider_payloads' => 'excluded',
                'raw_kyc_payloads' => 'excluded',
                'private_storage_coordinates' => 'excluded',
                'credentials_and_secrets' => 'excluded',
                'account_and_contact_identifiers' => 'masked',
            ],
        ];
    }

    /** @param list<array<string, mixed>> $deliveries */
    private function deliverySummaries(array $deliveries): array
    {
        return array_map(
            static fn (array $delivery): array => Arr::only($delivery, [
                'delivery_id',
                'channel',
                'status',
                'provider_status',
                'attempt_count',
                'last_attempted_at',
                'delivered_at',
            ]),
            $deliveries,
        );
    }

    /** @param list<array<string, mixed>> $entries */
    private function journalSummaries(array $entries): array
    {
        return array_map(
            static fn (array $entry): array => Arr::only($entry, [
                'id',
                'reference_number',
                'event_type',
                'correlation_id',
                'causation_id',
                'occurred_at',
                'created_at',
            ]),
            $entries,
        );
    }

    /** @return array<string, mixed> */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return list<array<string, mixed>> */
    private function list(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, 'is_array'))
            : [];
    }
}
