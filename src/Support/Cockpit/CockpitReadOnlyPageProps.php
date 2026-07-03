<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Cockpit;

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

class CockpitReadOnlyPageProps
{
    public function __construct(private readonly CockpitReadModelProviderContract $readModels)
    {
    }

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
            'read_model' => $this->readModels->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                include: ['voucher', 'execution', 'journal', 'actions', 'feedback'],
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayCodeExplorerArray(): array
    {
        return [
            ...$this->toArray(),
            'pay_codes_read_model' => $this->readModels->forPayCodeList(new CockpitReadModelQueryData(
                include: ['voucher'],
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDashboardArray(): array
    {
        return [
            ...$this->toArray(),
            'dashboard_read_model' => $this->readModels->forDashboard(new CockpitReadModelQueryData(
                include: ['voucher'],
            ))->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuickGenerateArray(): array
    {
        return [
            ...$this->toArray(),
            'quick_generate_read_model' => $this->readModels->forQuickGenerate(new CockpitReadModelQueryData(
                include: ['templates', 'pricing'],
            ))->toArray(),
        ];
    }
}
