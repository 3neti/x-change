<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Cockpit;

class CockpitReadOnlyPageProps
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $code = null): array
    {
        return [
            'can' => [
                'view_cockpit' => true,
                'mutate_vouchers' => false,
                'execute_drivers' => false,
                'write_journal_entries' => false,
                'send_feedback' => false,
                'call_providers' => false,
                'move_money' => false,
            ],
            'redaction' => [
                'policy' => 'default-cockpit-redaction',
                'payloads' => 'redacted-until-authorized-read-models-exist',
                'sensitive_fields' => [
                    'account_number',
                    'authorization',
                    'bank_account',
                    'email',
                    'mobile',
                    'otp',
                    'provider_payload',
                    'raw_payload',
                    'reference_id',
                    'secret',
                    'token',
                    'webhook',
                ],
            ],
            'context' => [
                'code' => $code,
            ],
        ];
    }
}
