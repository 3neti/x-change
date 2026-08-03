<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class CockpitPayCodeDetailProjection
{
    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function overview(array $detail): array
    {
        return [
            'schema' => 'x-change.cockpit.pay-code-overview.v1',
            'capability' => $this->array($detail['capability'] ?? []),
            'party' => $this->array($detail['party'] ?? []),
            'amounts' => $this->list($detail['amounts'] ?? []),
            'timing' => [
                'issued_at' => $detail['created_at'] ?? null,
                'starts_at' => $detail['starts_at'] ?? null,
                'expires_at' => $detail['expires_at'] ?? null,
                'redeemed_at' => $detail['redeemed_at'] ?? null,
            ],
            'claim_count' => count($this->list($detail['claims'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function instructions(array $detail): array
    {
        $instructions = $this->array($detail['instructions'] ?? []);
        $cash = $this->array($instructions['cash'] ?? []);
        $validation = $this->array($cash['validation'] ?? []);
        $inputs = $this->list(data_get($instructions, 'inputs.fields', []));
        $feedback = $this->array($instructions['feedback'] ?? []);
        $rider = $this->array($instructions['rider'] ?? []);
        $execution = $this->array($instructions['execution'] ?? []);

        return [
            'schema' => 'x-change.cockpit.pay-code-instructions.v1',
            'groups' => array_values(array_filter([
                $this->group('value', 'Value', 'banknote', [
                    $this->fact('Amount', $cash['amount'] ?? null),
                    $this->fact('Currency', $cash['currency'] ?? null),
                    $this->fact('Settlement Rail', $cash['settlement_rail'] ?? null),
                    $this->fact('Fee Strategy', $cash['fee_strategy'] ?? null),
                ]),
                $this->group('claim', 'Claim Requirements', 'shield-check', [
                    $this->fact('Required Inputs', $this->labels($inputs)),
                    $this->fact('Validations', $this->configuredKeys($validation)),
                    $this->fact('Target Mobile', $this->maskMobile($validation['mobile'] ?? null)),
                    $this->fact('Vendor', $validation['payable'] ?? null),
                    $this->fact('Execution Driver', $execution['driver'] ?? null),
                ]),
                $this->group('experience', 'Recipient Experience', 'sparkles', [
                    $this->fact('Message', $rider['message'] ?? null),
                    $this->fact('Action Link', $this->safeUrl($rider['url'] ?? null)),
                    $this->fact('Splash', filled($rider['splash'] ?? null) ? 'Configured' : null),
                    $this->fact('Stamp', $this->stampLabel($rider['stamp'] ?? null)),
                ]),
                $this->group('delivery', 'Notifications', 'send', [
                    $this->fact('Channels', $this->configuredKeys($feedback)),
                ]),
                $this->group('controls', 'Lifecycle Controls', 'sliders-horizontal', [
                    $this->fact('Quantity', $instructions['count'] ?? null),
                    $this->fact('Prefix', $instructions['prefix'] ?? null),
                    $this->fact('Voucher Type', $instructions['voucher_type'] ?? null),
                    $this->fact('Target Amount', $instructions['target_amount'] ?? null),
                    $this->fact('Onboarding', ($instructions['onboarding'] ?? false) === true ? 'Required' : null),
                ]),
            ])),
            'raw_payload_exposed' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function claims(array $detail): array
    {
        return [
            'schema' => 'x-change.cockpit.pay-code-claims.v1',
            'records' => $this->list($detail['claims'] ?? []),
            'evidence' => $this->list($detail['claim_evidence'] ?? []),
            'redactions' => [
                'secrets_exposed' => false,
                'otp_values_exposed' => false,
                'binary_evidence_in_page_props' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function settlement(array $detail): array
    {
        return [
            'schema' => 'x-change.cockpit.pay-code-settlement.v1',
            'envelope' => $this->array($detail['settlement_envelope'] ?? []),
            'redactions' => [
                'payload_exposed' => false,
                'direct_attachment_urls_exposed' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function treasury(array $detail): array
    {
        return [
            'schema' => 'x-change.cockpit.pay-code-treasury.v1',
            'backing' => $this->array($detail['backing'] ?? []),
            'redemption' => $this->array($detail['redemption'] ?? []),
            'provider_calls_on_read' => false,
        ];
    }

    /** @param array<int, array<string, mixed>|null> $facts */
    private function group(string $key, string $label, string $icon, array $facts): ?array
    {
        $facts = array_values(array_filter($facts));

        return $facts === [] ? null : compact('key', 'label', 'icon', 'facts');
    }

    private function fact(string $label, mixed $value): ?array
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return [
            'label' => $label,
            'value' => Str::limit(trim((string) $value), 180),
        ];
    }

    /** @return array<int, string> */
    private function configuredKeys(array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => filled($value) && $value !== false)
            ->keys()
            ->map(fn (mixed $key): string => Str::headline((string) $key))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function labels(array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => is_scalar($value) && filled($value))
            ->map(fn (mixed $value): string => Str::headline((string) $value))
            ->values()
            ->all();
    }

    private function maskMobile(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return is_string($digits) && strlen($digits) >= 4
            ? '•••• '.substr($digits, -4)
            : null;
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return Str::limit($value, 120);
    }

    private function stampLabel(mixed $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return Str::headline((string) ($value['source'] ?? $value['artwork_source'] ?? 'configured'));
    }

    /** @return array<string, mixed> */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return array<int, mixed> */
    private function list(mixed $value): array
    {
        return is_array($value) && Arr::isList($value) ? $value : [];
    }
}
