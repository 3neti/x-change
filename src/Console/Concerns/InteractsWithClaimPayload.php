<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Concerns;

trait InteractsWithClaimPayload
{
    /**
     * @return array<string, mixed>
     */
    protected function claimPayloadFromOptions(): array
    {
        $payload = [];

        foreach ([
            'mobile' => 'mobile',
            'country' => 'country',
            'bank-code' => 'bank_code',
            'account-number' => 'account_number',
            'secret' => 'secret',
            'flow-id' => 'flow_id',
            'reference-id' => 'reference_id',
        ] as $option => $field) {
            $value = $this->option($option);

            if (is_string($value) && trim($value) !== '') {
                $payload[$field] = trim($value);
            }
        }

        $amount = $this->option('amount');
        if ($amount !== null && $amount !== '') {
            $payload['amount'] = (float) $amount;
        }

        $rawPayload = $this->option('payload');
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decoded = json_decode($rawPayload, true);

            if (is_array($decoded)) {
                $payload = array_replace_recursive($payload, $decoded);
            }
        }

        $meta = $this->commandMetaPayload();
        if ($meta !== []) {
            $payload['_meta'] = $meta;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function commandMetaPayload(): array
    {
        $meta = [];

        $idempotencyKey = $this->option('idempotency-key');
        if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
            $meta['idempotency_key'] = trim($idempotencyKey);
        }

        return $meta;
    }
}
