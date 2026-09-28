<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialPrincipal;
use RuntimeException;

final readonly class CampaignCommercialSponsorshipResolver
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
    ) {}

    public function resolve(
        CampaignWorksheetAuthorization $authorization,
        Model $maker,
    ): ?CommercialPrincipal {
        $sponsorship = (array) data_get(
            $authorization->instruction_blueprint_ciphertext,
            'commercial_sponsorship',
            [],
        );

        if ($sponsorship === []) {
            return null;
        }

        if (data_get($sponsorship, 'schema') !== 'x-change.commercial-pay-code-sponsorship.v1') {
            throw new RuntimeException('The campaign Commercial Principal sponsorship schema is unsupported.');
        }

        if (! $this->authority->allows($maker, CommercialOperatorCapability::PreparePayCodes)) {
            throw new RuntimeException('The campaign maker is not authorized to prepare Commercial Pay Codes.');
        }

        $checker = $this->checker($authorization);

        if (! $this->authority->allows($checker, CommercialOperatorCapability::ApprovePayCodes)) {
            throw new RuntimeException('The campaign checker is not authorized to approve Commercial Pay Codes.');
        }

        $principal = CommercialPrincipal::query()
            ->where('reference', trim((string) data_get($sponsorship, 'principal_reference')))
            ->where('active', true)
            ->first();

        if (! $principal instanceof CommercialPrincipal) {
            throw new RuntimeException('The campaign Commercial Principal is unavailable or inactive.');
        }

        $expectedHash = trim((string) data_get($sponsorship, 'instruction_hash'));
        $actualHash = hash('sha256', json_encode([
            'principal_reference' => $principal->reference,
            'maker_type' => $maker->getMorphClass(),
            'maker_id' => (string) $maker->getKey(),
            'checker_type' => $checker->getMorphClass(),
            'checker_id' => (string) $checker->getKey(),
            'run_reference' => trim((string) data_get($sponsorship, 'run_reference')),
            'principal_minor' => (int) $authorization->principal_minor,
            'currency' => (string) $authorization->currency,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($expectedHash === '' || ! hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException('The campaign Commercial Principal sponsorship is no longer valid for this authorization.');
        }

        return $principal;
    }

    private function checker(CampaignWorksheetAuthorization $authorization): Model
    {
        $type = trim((string) $authorization->approved_by_type);
        $id = trim((string) $authorization->approved_by_id);
        $modelClass = Relation::getMorphedModel($type) ?? $type;

        if ($type === '' || $id === '' || ! is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException('The campaign authorization has no resolvable checker.');
        }

        /** @var Model|null $checker */
        $checker = $modelClass::query()->find($id);

        if (! $checker instanceof Model || $checker->getMorphClass() !== $type) {
            throw new RuntimeException('The campaign authorization checker could not be resolved.');
        }

        return $checker;
    }
}
