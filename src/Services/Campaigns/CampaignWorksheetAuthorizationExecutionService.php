<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Campaigns\ApproveCampaignWorksheetAuthorization;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Data\Settlement\SettlementExecutionResultData;
use RuntimeException;

final readonly class CampaignWorksheetAuthorizationExecutionService implements SettlementExecutionContract
{
    public function __construct(
        private ApproveCampaignWorksheetAuthorization $authorizations,
        private UserResolverContract $users,
    ) {}

    public function execute(Voucher $voucher, array $payload): SettlementExecutionResultData
    {
        $officer = $this->users->resolve($payload);
        if (! $officer instanceof Model) {
            throw new RuntimeException('An authenticated officer is required to approve a campaign worksheet.');
        }

        $authorization = $this->authorizations->handle((string) $voucher->code, $officer);

        return new SettlementExecutionResultData(
            voucher_code: (string) $voucher->code,
            status: 'authorized',
            message: 'Campaign worksheet authorized. Beneficiary issuance has not started.',
            meta: [
                'authorization_reference' => $authorization->reference,
                'worksheet_reference' => $authorization->worksheet?->reference,
                'beneficiary_count' => $authorization->beneficiary_count,
                'principal_minor' => $authorization->principal_minor,
                'currency' => $authorization->currency,
            ],
        );
    }
}
