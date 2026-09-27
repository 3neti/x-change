<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Legal;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DeferredOnboardingFunding;
use LBHurtado\XChange\Services\Funding\PayCodeFundingEligibility;
use LBHurtado\XChange\Services\Legal\CurrentAgreementService;
use RuntimeException;

final readonly class DeferOnboardingFundingUntilAgreement
{
    public function __construct(
        private CurrentAgreementService $agreements,
        private PayCodeFundingEligibility $eligibility,
    ) {}

    public function handle(
        Voucher $voucher,
        Authenticatable&Model $claimant,
    ): ?DeferredOnboardingFunding {
        if (! $this->agreements->enabled()) {
            return null;
        }

        $document = $this->agreements->document();

        if ($this->agreements->hasAccepted($claimant, $document)) {
            return null;
        }

        $decision = $this->eligibility->evaluate($voucher);

        if (
            ! $decision->eligible
            || $decision->amountMinor === null
            || $decision->currency === null
            || $decision->connectionReference === null
            || $decision->reservationOperationReference === null
        ) {
            throw new RuntimeException($decision->message);
        }

        $attributes = [
            'subject_type' => $claimant->getMorphClass(),
            'subject_id' => (string) $claimant->getKey(),
            'required_agreement_key' => $document->key,
            'required_agreement_version' => $document->version,
            'required_agreement_sha256' => $document->sha256,
            'amount_minor' => $decision->amountMinor,
            'currency' => $decision->currency,
            'connection_reference' => $decision->connectionReference,
            'reservation_operation_reference' => $decision->reservationOperationReference,
        ];
        $deferred = DeferredOnboardingFunding::query()->firstOrCreate([
            'voucher_id' => $voucher->getKey(),
        ], [
            'reference' => (string) Str::ulid(),
            ...$attributes,
            'status' => 'pending_agreement',
            'deferred_at' => now(),
        ]);

        foreach ($attributes as $key => $value) {
            if ((string) $deferred->getAttribute($key) !== (string) $value) {
                throw new RuntimeException('The deferred onboarding funding evidence conflicts with its original reservation.');
            }
        }

        return $deferred;
    }
}
