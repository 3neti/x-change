<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Number;
use LBHurtado\XChange\Models\FundingIntent;

class FundingInstructionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function forIntent(FundingIntent $intent): array
    {
        $instructions = (array) $intent->instructions_ciphertext;
        $display = is_array($instructions['display_data'] ?? null)
            ? $instructions['display_data']
            : [];
        $simulationOnly = $intent->provider_code === 'qrph_simulator';

        return [
            'reference' => $intent->reference,
            'provider' => $intent->provider_code,
            'amount' => Number::currency($intent->expected_amount_minor / 100, in: $intent->currency),
            'currency' => $intent->currency,
            'status' => $intent->status->value,
            'expires_at' => $intent->expires_at?->toIso8601String(),
            'funding_address' => $this->optionalString($instructions['funding_address'] ?? null),
            'action_url' => $this->safeActionUrl($instructions['action_url'] ?? null),
            'institution' => $this->optionalString($display['institution'] ?? null),
            'account_name' => $this->optionalString($display['account_name'] ?? null),
            'delivery' => $this->optionalString($display['delivery'] ?? null),
            'qr_code' => $this->qrCode($instructions['qr_code'] ?? null),
            'qr_mode' => $this->optionalString(data_get($instructions, 'qr_code.qr_mode')),
            'transaction_type' => $this->optionalString(data_get($instructions, 'qr_code.transaction_type')),
            'embedded_amount' => data_get($instructions, 'qr_code.embedded_amount') === true,
            'provider_generated' => data_get($instructions, 'qr_code.provider_generated') === true,
            'balance_changed' => false,
            'simulation_only' => $simulationOnly,
            'sensitive' => ! $simulationOnly,
        ];
    }

    private function qrCode(mixed $value): ?string
    {
        if (! is_array($value) || ($value['mime_type'] ?? null) !== 'image/png') {
            return null;
        }

        $encoded = $this->optionalString($value['base64_payload'] ?? null);

        if ($encoded === null) {
            return null;
        }

        $decoded = base64_decode($encoded, true);

        if (! is_string($decoded) || ! str_starts_with($decoded, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return 'data:image/png;base64,'.$encoded;
    }

    private function safeActionUrl(mixed $value): ?string
    {
        $url = $this->optionalString($value);

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['https'], true) ? $url : null;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
