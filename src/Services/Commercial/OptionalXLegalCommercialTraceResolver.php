<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use DateTimeImmutable;
use LBHurtado\XChange\Contracts\CommercialLegalTraceResolverContract;
use LBHurtado\XCommerce\Data\CommercialLegalTraceData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final class OptionalXLegalCommercialTraceResolver implements CommercialLegalTraceResolverContract
{
    private const EvaluationContract = 'LBHurtado\\XLegal\\Contracts\\CommercialLegalConstraintEvaluationContract';

    private const OperationContext = 'LBHurtado\\XLegal\\ValueObjects\\CommercialOperationContext';

    private const JurisdictionContext = 'LBHurtado\\XLegal\\ValueObjects\\JurisdictionContext';

    public function forPublication(CommercialOfferingData $offering): CommercialOfferingData
    {
        $available = interface_exists(self::EvaluationContract)
            && class_exists(self::OperationContext)
            && class_exists(self::JurisdictionContext)
            && app()->bound(self::EvaluationContract);

        if (! $available && $this->enforcementRequired()) {
            throw new \DomainException(
                'x-legal is required before Commercial Offerings may be published.',
            );
        }

        if (! $available) {
            return $offering;
        }

        $jurisdictionClass = self::JurisdictionContext;
        $contextClass = self::OperationContext;
        $jurisdiction = new $jurisdictionClass(
            $offering->legalTrace->jurisdiction,
            $offering->legalTrace->legalEntityReference,
            $offering->legalTrace->profile,
        );
        $context = new $contextClass(
            operation: 'commercial_offering_publication',
            principalReference: (string) config('x-change.treasury.principal_reference', 'principal:system'),
            mandateReference: (string) config('x-change.treasury.system_mandate_reference', 'mandate:system:treasury'),
            commercialOfferingReference: $offering->reference,
            amountMinor: 0,
            currency: $offering->catalog->currency,
            purpose: 'publish_commercial_offering',
            idempotencyKey: 'commercial-offering-publication:'.$offering->snapshotHash(),
            effectiveAt: new DateTimeImmutable($offering->effectiveAt),
            jurisdiction: $jurisdiction,
            traceReferences: $offering->legalTrace->traceReferences,
        );
        $decision = app(self::EvaluationContract)->evaluate($context);
        $decisionValue = (string) $decision->decision->value;

        if ($this->enforcementRequired() && $decisionValue !== 'allowed') {
            throw new \DomainException(
                "x-legal did not authorize Commercial Offering publication: {$decision->reason}",
            );
        }

        return new CommercialOfferingData(
            reference: $offering->reference,
            version: $offering->version,
            catalog: $offering->catalog,
            waterfallPolicy: $offering->waterfallPolicy,
            attributionPolicy: $offering->attributionPolicy,
            legalTrace: new CommercialLegalTraceData(
                jurisdiction: $offering->legalTrace->jurisdiction,
                legalEntityReference: $offering->legalTrace->legalEntityReference,
                profile: (string) $decision->profile,
                profileVersion: (string) $decision->profileVersion,
                decision: $decisionValue,
                decisionReferences: $offering->legalTrace->decisionReferences,
                invariantReferences: $offering->legalTrace->invariantReferences,
                traceReferences: array_values(array_unique([
                    ...$offering->legalTrace->traceReferences,
                    ...$decision->traceReferences,
                ])),
            ),
            effectiveAt: $offering->effectiveAt,
        );
    }

    private function enforcementRequired(): bool
    {
        return (string) config('x-change.commercial.legal.enforcement', 'advisory') === 'required';
    }
}
