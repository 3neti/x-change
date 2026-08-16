<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;
use LBHurtado\XProvisioning\Data\CommercialRecipientDesignationData;
use LBHurtado\XProvisioning\Enums\CommercialSettlementAccountBinding;
use LBHurtado\XProvisioning\Enums\CommercialSettlementDisposition;

final readonly class CommercialRecipientDesignationProvisioningSnapshotFactory
{
    /** @return array<string, mixed> */
    public function make(string $purpose): array
    {
        $policy = (array) config('x-change.provisioning.commercial_recipient_designation', []);
        $predecessor = trim((string) ($policy['supersedes_designation_reference'] ?? ''));
        $designationReference = trim((string) ($policy['settlement_designation_reference'] ?? ''));

        if ($predecessor === '' || $designationReference === '') {
            throw new \DomainException('Commercial recipient provisioning requires predecessor and replacement designation references.');
        }

        $counterparty = trim((string) ($policy['counterparty_reference'] ?? ''));
        $role = trim((string) ($policy['commercial_role'] ?? ''));
        $agreement = trim((string) ($policy['agreement_reference'] ?? ''));
        $taxProfile = isset($policy['tax_profile_reference'])
            ? trim((string) $policy['tax_profile_reference'])
            : null;
        $components = [];

        foreach ($this->activeEconomics() as $economics) {
            foreach ($economics->components as $component) {
                foreach ($component->allocationSchedule?->rules ?? [] as $rule) {
                    if ($rule->designationReference !== $predecessor) {
                        continue;
                    }

                    if ($rule->destinationKind !== CommercialAllocationDestinationKind::ExternalRecipient
                        || $rule->recipientReference !== $counterparty
                        || $rule->participantRole !== $role
                        || $rule->agreementReference !== $agreement
                        || $rule->taxPolicyReference !== $taxProfile) {
                        throw new \DomainException('Active Component Economics does not match the configured recipient transition authority.');
                    }

                    $components[] = $component->componentReference;
                }
            }
        }

        $components = array_values(array_unique($components));
        sort($components, SORT_STRING);

        if ($components === []) {
            throw new \DomainException("No active Component Economics references predecessor designation [{$predecessor}].");
        }

        $designation = new CommercialRecipientDesignationData(
            counterpartyReference: $counterparty,
            commercialRole: $role,
            componentScope: $components,
            agreementReference: $agreement,
            settlementDesignationReference: $designationReference,
            taxProfileReference: $taxProfile,
            effectiveFrom: CarbonImmutable::now()->startOfSecond()->toAtomString(),
            settlementDisposition: CommercialSettlementDisposition::InternalAccountCredit,
            settlementAccountBinding: CommercialSettlementAccountBinding::AcceptedCandidateAccount,
            supersedesDesignationReference: $predecessor,
        );

        return [
            ...$designation->toArray(),
            'label' => (string) ($policy['label'] ?? 'Commercial Recipient Account Credit'),
            'purpose' => trim($purpose),
            'activation_gate' => 'recipient_acceptance_and_economics_switch',
        ];
    }

    /** @return list<CommercialComponentEconomicsSetData> */
    private function activeEconomics(): array
    {
        $profiles = array_values(array_map(
            'strval',
            (array) config('x-change.commercial.offerings.profiles', ['pay_code', 'account_funding']),
        ));
        $heads = CommercialComponentEconomicsHead::query()
            ->with('currentActivation.economics')
            ->whereIn('profile', $profiles)
            ->get()
            ->keyBy('profile');

        return array_map(function (string $profile) use ($heads) {
            $economics = $heads->get($profile)?->currentActivation?->economics;

            if ($economics === null) {
                throw new \DomainException("Commercial Component Economics profile [{$profile}] is not active.");
            }

            return $economics->economics();
        }, $profiles);
    }
}
