<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EngageSpark\Classes\ServiceMode;
use LBHurtado\EngageSpark\EngageSpark;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAuditLog;
use LBHurtado\SettlementEnvelope\Models\EnvelopePayloadVersion;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\XChange\Actions\Campaigns\BindCampaignPaymentQr;
use LBHurtado\XChange\Actions\Claim\SubmitCompiledFormClaim;
use LBHurtado\XChange\Actions\Leads\CreateLeadCampaign;
use LBHurtado\XChange\Actions\Payment\InspectCampaignPaymentEvidence;
use LBHurtado\XChange\Actions\Payment\RecognizeQualifyingCampaignPayment;
use LBHurtado\XChange\Actions\Redemption\PrepareVoucherClaimEvidence;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Actions\Settlement\ApproveCampaignPolicyCompletion;
use LBHurtado\XChange\Actions\Settlement\BindProvisionalCoverage;
use LBHurtado\XChange\Actions\Settlement\CheckCampaignPolicyCompletionTransportReadiness;
use LBHurtado\XChange\Actions\Settlement\IssueCompletionPayCode;
use LBHurtado\XChange\Actions\Settlement\OrchestrateProvisionalCoverage;
use LBHurtado\XChange\Actions\Settlement\PrepareCampaignPolicyCompletion;
use LBHurtado\XChange\Actions\Settlement\ProjectCompletionClaimEvidence;
use LBHurtado\XChange\Actions\Settlement\RecordCampaignPolicyCompletionOutcome;
use LBHurtado\XChange\Actions\Settlement\RequestCampaignPolicyCompletion;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Data\Settlement\CampaignCoverageDecisionData;
use LBHurtado\XChange\Data\Settlement\CompletionPayCodeInstructionsData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionOutcomeData;
use LBHurtado\XChange\Data\Settlement\ProvisionalCoverageTermsData;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Enums\CampaignPaymentAmountMode;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Events\CampaignPaymentRecognized;
use LBHurtado\XChange\Events\CompletionClaimEvidenceProjected;
use LBHurtado\XChange\Events\CompletionPayCodeIssued;
use LBHurtado\XChange\Events\PolicyCompletionAuthorized;
use LBHurtado\XChange\Events\PolicyCompletionOutcomeRecorded;
use LBHurtado\XChange\Events\PolicyCompletionRequested;
use LBHurtado\XChange\Events\ProvisionalCoverageBound;
use LBHurtado\XChange\Exceptions\CampaignCoverageDriverUnavailable;
use LBHurtado\XChange\Exceptions\CampaignPolicyCompletionDriverUnavailable;
use LBHurtado\XChange\Jobs\Campaigns\AdvanceCampaignPaymentLifecycleJob;
use LBHurtado\XChange\Jobs\Feedback\DeliverQueuedFeedbackSmsJob;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\CampaignPaymentEvidenceQuarantine;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Models\PolicyCompletionRequest;
use LBHurtado\XChange\Models\ProvisionalCoverage;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingQrArtifact;
use LBHurtado\XChange\Services\Cockpit\CampaignPaymentEvidenceAttentionReadModel;
use LBHurtado\XChange\Services\Cockpit\CampaignPolicyLifecycleReadModel;
use LBHurtado\XChange\Services\Cockpit\CampaignPolicyLifecycleStageResolver;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentCampaignCoverageDriver;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentPolicyCompletionDriver;
use LBHurtado\XChange\Services\Settlement\CampaignCoverageDriverRegistry;
use LBHurtado\XChange\Services\Settlement\CampaignPolicyCompletionDriverRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeCampaignCoverageDriver;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XFeedback\Contracts\FeedbackChannelDriverContract;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Contracts\FeedbackDeliveryAttemptRecorderContract;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;

it('immutably binds one reusable payment QR to one campaign revision', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $availableFrom = CarbonImmutable::parse('2026-09-23T00:00:00Z');
    $availableUntil = CarbonImmutable::parse('2026-10-23T00:00:00Z');

    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 12_200,
        availableFrom: $availableFrom,
        availableUntil: $availableUntil,
        permittedPaymentRules: [
            'maximum_payments' => 500,
            'allowed_rails' => ['INSTAPAY'],
        ],
    );
    $replayed = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 12_200,
        availableFrom: $availableFrom,
        availableUntil: $availableUntil,
        permittedPaymentRules: [
            'allowed_rails' => ['INSTAPAY'],
            'maximum_payments' => 500,
        ],
    );

    expect($binding->entry_mode)->toBe(CampaignEntryMode::ReusablePaymentQr)
        ->and($binding->amount_mode)->toBe(CampaignPaymentAmountMode::Fixed)
        ->and($binding->fixed_amount_minor)->toBe(12_200)
        ->and($binding->campaign_revision_id)->toBe($campaign->active_template_version_id)
        ->and($binding->campaign->is($campaign))->toBeTrue()
        ->and($binding->standingFundingAddress->is($address))->toBeTrue()
        ->and($binding->qrArtifact->is($artifact))->toBeTrue()
        ->and($replayed->is($binding))->toBeTrue()
        ->and(CampaignPaymentQrBinding::query()->count())->toBe(1);

    expect(fn () => $binding->forceFill(['fixed_amount_minor' => 1])->save())
        ->toThrow(LogicException::class, 'immutable');
    expect(fn () => $binding->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('rejects ordinary endpoint campaigns and conflicting revision bindings', function (): void {
    $owner = campaignPaymentQrOwner();
    $ordinary = campaignPaymentQrCampaign($owner, CampaignEntryMode::PayCodeOnOpen);
    [$address, $artifact] = campaignPaymentQrAddress($owner);

    expect(fn () => app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $ordinary,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
    ))->toThrow(ValidationException::class, 'reusable-payment-QR');

    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    $action = app(BindCampaignPaymentQr::class);
    $action->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
        permittedPaymentRules: ['allowed_rails' => ['INSTAPAY']],
    );

    expect(fn () => $action->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
        permittedPaymentRules: ['allowed_rails' => ['PESONET']],
    ))->toThrow(ValidationException::class, 'already bound');
});

it('requires an active payment-purpose static QR and coherent amount availability', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner, FundingAddressPurpose::AccountFunding);

    expect(fn () => app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 0,
        availableFrom: CarbonImmutable::parse('2026-09-24T00:00:00Z'),
        availableUntil: CarbonImmutable::parse('2026-09-23T00:00:00Z'),
    ))->toThrow(ValidationException::class, 'payment-purpose QR');
});

it('quarantines adverse and incompatible campaign payment evidence without financial effects', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
    );
    $before = campaignPaymentFinancialCounts();

    $returned = campaignPaymentObservation($address, 'transaction-returned', 'returned');
    $first = app(InspectCampaignPaymentEvidence::class)->handle($binding, $returned);
    $replayed = app(InspectCampaignPaymentEvidence::class)->handle($binding, $returned);
    $settled = campaignPaymentObservation($address, 'transaction-conflict', 'settled');
    campaignPaymentObservation($address, 'transaction-conflict', 'returned');
    $incompatible = app(InspectCampaignPaymentEvidence::class)->handle($binding, $settled);

    expect($first)->not->toBeNull()
        ->and($first?->reason_code)->toBe('adverse_status')
        ->and($first?->reason_detail)->toBe('returned')
        ->and($replayed?->is($first))->toBeTrue()
        ->and($incompatible?->reason_code)->toBe('incompatible_evidence')
        ->and($incompatible?->reason_detail)->toBe('status_transition:settled:returned')
        ->and(CampaignPaymentEvidenceQuarantine::query()->count())->toBe(2)
        ->and(campaignPaymentFinancialCounts())->toBe($before);

    expect(fn () => $first?->forceFill(['reason_code' => 'changed'])->save())
        ->toThrow(LogicException::class, 'immutable');
    expect(fn () => $first?->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('keeps compatible evidence clear and exposes only aggregate campaign attention', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
    );

    $settled = campaignPaymentObservation($address, 'transaction-settled', 'settled');
    $unknown = campaignPaymentObservation($address, 'transaction-unknown', 'mystery');

    expect(app(InspectCampaignPaymentEvidence::class)->handle($binding, $settled))->toBeNull();
    app(InspectCampaignPaymentEvidence::class)->handle($binding, $unknown);

    $attention = app(CampaignPaymentEvidenceAttentionReadModel::class)
        ->forCampaigns(LeadCampaign::query()->whereKey($campaign->getKey())->get());
    $row = $attention[$campaign->getKey()];

    expect($row)->toMatchArray([
        'count' => 1,
        'status' => 'needs_attention',
        'label' => 'Needs attention',
        'latest_reason' => 'unknown_status',
    ])
        ->and(json_encode($row))->not->toContain('transaction-unknown')
        ->and(json_encode($row))->not->toContain('provider_transaction');
});

it('recognizes one qualifying fixed campaign payment exactly once and emits a redacted DTO event', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 12_200,
        permittedPaymentRules: [
            'allowed_rails' => ['INSTAPAY'],
            'maximum_payments' => 5,
        ],
    );
    $observation = campaignPaymentObservation($address, 'transaction-recognized', 'settled');
    $before = campaignPaymentFinancialCounts();
    Event::fake([CampaignPaymentRecognized::class]);

    $first = app(RecognizeQualifyingCampaignPayment::class)->handle($binding, $observation);
    $replayed = app(RecognizeQualifyingCampaignPayment::class)->handle($binding, $observation);

    expect($first->recognized())->toBeTrue()
        ->and($replayed->recognition?->is($first->recognition))->toBeTrue()
        ->and(CampaignPaymentRecognition::query()->count())->toBe(1)
        ->and(CampaignPaymentEvidenceQuarantine::query()->count())->toBe(0)
        ->and(campaignPaymentFinancialCounts())->toBe($before);

    Event::assertDispatchedTimes(CampaignPaymentRecognized::class, 1);
    Event::assertDispatched(CampaignPaymentRecognized::class, function (
        CampaignPaymentRecognized $event,
    ): bool {
        $payload = json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);

        return $event->recognition->grossAmountMinor === 12_200
            && ! str_contains($payload, 'transaction-recognized')
            && ! str_contains($payload, 'provider_transaction')
            && ! str_contains($payload, 'payer');
    });

    expect(fn () => $first->recognition?->forceFill(['gross_amount_minor' => 1])->save())
        ->toThrow(LogicException::class, 'immutable');
    expect(fn () => $first->recognition?->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('waits for settlement and quarantines terminal payments that fail binding rules', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 10_000,
        permittedPaymentRules: ['allowed_rails' => ['INSTAPAY']],
    );
    $pending = campaignPaymentObservation($address, 'transaction-rule-failure', 'pending');

    $waiting = app(RecognizeQualifyingCampaignPayment::class)->handle($binding, $pending);
    $settled = campaignPaymentObservation($address, 'transaction-rule-failure', 'settled');
    $rejected = app(RecognizeQualifyingCampaignPayment::class)->handle($binding, $settled);

    expect($waiting->recognized())->toBeFalse()
        ->and($waiting->quarantined())->toBeFalse()
        ->and($rejected->quarantined())->toBeTrue()
        ->and($rejected->quarantine?->reason_code)->toBe('qualification_rejected')
        ->and($rejected->quarantine?->reason_detail)->toBe('fixed_amount_mismatch')
        ->and(CampaignPaymentRecognition::query()->count())->toBe(0);
});

it('enforces the maximum qualifying payments rule under the binding lock', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
        permittedPaymentRules: ['maximum_payments' => 1],
    );

    $first = app(RecognizeQualifyingCampaignPayment::class)->handle(
        $binding,
        campaignPaymentObservation($address, 'transaction-limit-1', 'settled'),
    );
    $second = app(RecognizeQualifyingCampaignPayment::class)->handle(
        $binding,
        campaignPaymentObservation($address, 'transaction-limit-2', 'settled'),
    );

    expect($first->recognized())->toBeTrue()
        ->and($second->quarantined())->toBeTrue()
        ->and($second->quarantine?->reason_detail)->toBe('maximum_payments_reached')
        ->and(CampaignPaymentRecognition::query()->count())->toBe(1);
});

it('atomically binds recognized payment to immutable provisional coverage and envelope', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition] = recognizedCampaignPayment();
    $before = campaignPaymentFinancialCounts();
    $terms = campaignCoverageTerms($recognition);
    Event::fake([ProvisionalCoverageBound::class]);

    $first = app(BindProvisionalCoverage::class)->handle($recognition, $terms);
    $replayed = app(BindProvisionalCoverage::class)->handle($recognition, $terms);

    expect($first->created)->toBeTrue()
        ->and($replayed->created)->toBeFalse()
        ->and($replayed->coverage->is($first->coverage))->toBeTrue()
        ->and($replayed->envelope->is($first->envelope))->toBeTrue()
        ->and(ProvisionalCoverage::query()->count())->toBe(1)
        ->and(Envelope::query()->count())->toBe(1)
        ->and(EnvelopePayloadVersion::query()->count())->toBe(1)
        ->and($first->envelope->payload_version)->toBe(1)
        ->and($first->envelope->reference_type)->toBe($recognition::class)
        ->and((int) $first->envelope->reference_id)->toBe($recognition->getKey())
        ->and(data_get($first->envelope->payload, 'coverage.coverage_reference'))
        ->toBe($first->coverage->reference)
        ->and(campaignPaymentFinancialCounts())->toBe($before);

    Event::assertDispatchedTimes(ProvisionalCoverageBound::class, 1);
    Event::assertDispatched(ProvisionalCoverageBound::class, function (
        ProvisionalCoverageBound $event,
    ): bool {
        $payload = json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);

        return ! str_contains($payload, 'transaction-coverage')
            && ! str_contains($payload, 'provider_transaction')
            && ! str_contains($payload, 'authority_reference')
            && ! str_contains($payload, 'payer');
    });

    expect(fn () => $first->coverage->forceFill(['coverage_type' => 'changed'])->save())
        ->toThrow(LogicException::class, 'immutable');
    expect(fn () => $first->coverage->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('rejects conflicting coverage replay without changing the original facts', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition] = recognizedCampaignPayment();
    $action = app(BindProvisionalCoverage::class);
    $first = $action->handle($recognition, campaignCoverageTerms($recognition));
    $conflict = campaignCoverageTerms($recognition, ['plan' => 'different']);

    expect(fn () => $action->handle($recognition, $conflict))
        ->toThrow(InvalidArgumentException::class, 'does not match')
        ->and(ProvisionalCoverage::query()->count())->toBe(1)
        ->and(Envelope::query()->count())->toBe(1)
        ->and(EnvelopePayloadVersion::query()->count())->toBe(1)
        ->and(ProvisionalCoverage::query()->sole()->is($first->coverage))->toBeTrue();
});

it('rolls back the envelope when provisional coverage persistence fails', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition] = recognizedCampaignPayment();
    Event::listen('eloquent.creating: '.ProvisionalCoverage::class, function (): never {
        throw new RuntimeException('forced coverage persistence failure');
    });

    expect(fn () => app(BindProvisionalCoverage::class)->handle(
        $recognition,
        campaignCoverageTerms($recognition),
    ))->toThrow(RuntimeException::class, 'forced coverage persistence failure')
        ->and(ProvisionalCoverage::query()->count())->toBe(0)
        ->and(Envelope::query()->count())->toBe(0)
        ->and(EnvelopePayloadVersion::query()->count())->toBe(0);
});

it('uses an explicitly selected driver to orchestrate coverage and converges on replay', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition] = recognizedCampaignPayment();
    $before = campaignPaymentFinancialCounts();
    $driver = new FakeCampaignCoverageDriver(
        id: 'campaign-provisional-coverage',
        version: '1.0.0',
        decision: CampaignCoverageDecisionData::eligible(
            campaignCoverageTerms($recognition),
            'recognized_payment_qualified',
        ),
    );
    $orchestrate = campaignCoverageOrchestrator($driver);

    $first = $orchestrate->handle(
        $recognition,
        'campaign-provisional-coverage',
        '1.0.0',
    );
    $replayed = $orchestrate->handle(
        $recognition,
        'campaign-provisional-coverage',
        '1.0.0',
    );

    expect($first->decision->eligible)->toBeTrue()
        ->and($first->decision->reasonCode)->toBe('recognized_payment_qualified')
        ->and($first->bound())->toBeTrue()
        ->and($first->binding?->created)->toBeTrue()
        ->and($replayed->binding?->created)->toBeFalse()
        ->and($replayed->binding?->coverage->is($first->binding?->coverage))->toBeTrue()
        ->and($driver->calls)->toBe(2)
        ->and(ProvisionalCoverage::query()->count())->toBe(1)
        ->and(Envelope::query()->count())->toBe(1)
        ->and(campaignPaymentFinancialCounts())->toBe($before);
});

it('separates the AUI premium from the insured amount and starts 24-hour coverage at settlement', function (): void {
    [$recognition] = recognizedCampaignPayment(5_000);
    $campaign = $recognition->campaignRecord();
    $campaign->forceFill([
        'settings' => [
            'entry_mode' => CampaignEntryMode::ReusablePaymentQr->value,
            'scenario_run' => [
                'reference' => 'RUN-AUI-PRODUCT-TERMS',
                'scenario' => 'aui_on_demand_insurance_payment',
                'envelope_driver_id' => AuiPersonalAccidentCampaignCoverageDriver::DRIVER_ID,
                'envelope_driver_version' => AuiPersonalAccidentCampaignCoverageDriver::DRIVER_VERSION,
                'product' => [
                    'name' => 'Cubao to Lucena Personal Accident Plan',
                    'premium_minor' => 5_000,
                    'insured_amount_minor' => 500_000,
                    'currency' => 'PHP',
                    'coverage_duration_hours' => 24,
                ],
            ],
        ],
    ])->save();

    $decision = app(AuiPersonalAccidentCampaignCoverageDriver::class)->decide($recognition);

    expect($decision->eligible)->toBeTrue()
        ->and($decision->terms?->coverageAmountMinor)->toBe(500_000)
        ->and($decision->terms?->effectiveAt?->equalTo($recognition->settled_at))->toBeTrue()
        ->and($decision->terms?->expiresAt?->equalTo($recognition->settled_at?->addHours(24)))->toBeTrue()
        ->and($decision->terms?->terms)->toMatchArray([
            'plan' => 'Cubao to Lucena Personal Accident Plan',
            'premium_minor' => 5_000,
            'coverage_duration_hours' => 24,
        ]);
});

it('rejects an AUI payment that does not match the configured premium', function (): void {
    [$recognition] = recognizedCampaignPayment(4_999);
    $campaign = $recognition->campaignRecord();
    $campaign->forceFill([
        'settings' => [
            'entry_mode' => CampaignEntryMode::ReusablePaymentQr->value,
            'scenario_run' => [
                'reference' => 'RUN-AUI-PREMIUM-MISMATCH',
                'scenario' => 'aui_on_demand_insurance_payment',
                'envelope_driver_id' => AuiPersonalAccidentCampaignCoverageDriver::DRIVER_ID,
                'envelope_driver_version' => AuiPersonalAccidentCampaignCoverageDriver::DRIVER_VERSION,
                'product' => [
                    'name' => 'Cubao to Lucena Personal Accident Plan',
                    'premium_minor' => 5_000,
                    'insured_amount_minor' => 500_000,
                    'currency' => 'PHP',
                    'coverage_duration_hours' => 24,
                ],
            ],
        ],
    ])->save();

    $decision = app(AuiPersonalAccidentCampaignCoverageDriver::class)->decide($recognition);

    expect($decision->eligible)->toBeFalse()
        ->and($decision->reasonCode)->toBe('campaign_or_payment_not_qualified');
});

it('stops an ineligible driver decision before persistence', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition] = recognizedCampaignPayment();
    $driver = new FakeCampaignCoverageDriver(
        id: 'campaign-provisional-coverage',
        version: '1.0.0',
        decision: CampaignCoverageDecisionData::ineligible('outside_driver_rules'),
    );

    $result = campaignCoverageOrchestrator($driver)->handle(
        $recognition,
        'campaign-provisional-coverage',
        '1.0.0',
    );

    expect($result->decision->eligible)->toBeFalse()
        ->and($result->decision->reasonCode)->toBe('outside_driver_rules')
        ->and($result->bound())->toBeFalse()
        ->and($driver->calls)->toBe(1)
        ->and(ProvisionalCoverage::query()->count())->toBe(0)
        ->and(Envelope::query()->count())->toBe(0);
});

it('fails closed for unavailable or identity-mismatched campaign coverage drivers', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition] = recognizedCampaignPayment();
    $driver = new FakeCampaignCoverageDriver(
        id: 'campaign-provisional-coverage',
        version: '1.0.0',
        decision: CampaignCoverageDecisionData::eligible(
            new ProvisionalCoverageTermsData(
                driverId: 'different-driver',
                driverVersion: '1.0.0',
                coverageType: 'provisional-service-coverage',
                currency: 'PHP',
                effectiveAt: $recognition->settled_at,
                authorization: [
                    'authority' => 'campaign-driver',
                    'authority_reference' => 'test-authorization',
                ],
            ),
        ),
    );
    $orchestrate = campaignCoverageOrchestrator($driver);

    expect(fn () => $orchestrate->handle(
        $recognition,
        'campaign-provisional-coverage',
        '2.0.0',
    ))->toThrow(CampaignCoverageDriverUnavailable::class)
        ->and(fn () => $orchestrate->handle(
            $recognition,
            'campaign-provisional-coverage',
            '1.0.0',
        ))->toThrow(InvalidArgumentException::class, 'different driver identity')
        ->and(ProvisionalCoverage::query()->count())->toBe(0)
        ->and(Envelope::query()->count())->toBe(0);
});

it('leaves no coverage facts when a campaign coverage driver fails', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition] = recognizedCampaignPayment();
    $driver = new FakeCampaignCoverageDriver(
        id: 'campaign-provisional-coverage',
        version: '1.0.0',
        failure: new RuntimeException('driver evaluation failed'),
    );

    expect(fn () => campaignCoverageOrchestrator($driver)->handle(
        $recognition,
        'campaign-provisional-coverage',
        '1.0.0',
    ))->toThrow(RuntimeException::class, 'driver evaluation failed')
        ->and(ProvisionalCoverage::query()->count())->toBe(0)
        ->and(Envelope::query()->count())->toBe(0);
});

it('issues exactly one zero-denominated completion Pay Code without moving money', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle(
        $recognition,
        campaignCoverageTerms($recognition),
    );
    $owner = $binding->standingFundingAddress->owner;
    $instructions = new CompletionPayCodeInstructionsData(
        applicantFields: ['name', 'mobile'],
        message: 'Complete the remaining application details.',
    );
    $before = campaignPaymentFinancialCounts();
    $walletValueBefore = (int) Transaction::query()->sum('amount');
    Event::fake([CompletionPayCodeIssued::class]);

    $first = app(IssueCompletionPayCode::class)->handle($bound->coverage, $owner, $instructions);
    $replayed = app(IssueCompletionPayCode::class)->handle($bound->coverage, $owner, $instructions);

    expect($first->created)->toBeTrue()
        ->and($replayed->created)->toBeFalse()
        ->and($replayed->voucher->is($first->voucher))->toBeTrue()
        ->and(CompletionPayCodeIssuance::query()->count())->toBe(1)
        ->and(Voucher::query()->whereKey($first->voucher->getKey())->count())->toBe(1)
        ->and((float) data_get($first->voucher->instructions, 'cash.amount'))->toBe(0.0)
        ->and(data_get($first->voucher->metadata, 'instructions.execution.driver'))
        ->toBe('campaign_coverage_completion')
        ->and(data_get($first->voucher->metadata, 'instructions.execution.metadata.post_redemption.mode'))
        ->toBe('execution_only')
        ->and(data_get($first->voucher->metadata, 'instructions.claim.default_outcome'))
        ->toBe('envelope_completion')
        ->and(AccountFundingReceipt::query()->count())->toBe($before['account_funding_receipts'])
        ->and(FundingSettlement::query()->count())->toBe($before['funding_settlements'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe($before['treasury_operations'])
        ->and((int) Transaction::query()->sum('amount'))->toBe($walletValueBefore);

    Event::assertDispatchedTimes(CompletionPayCodeIssued::class, 1);
    Event::assertDispatched(CompletionPayCodeIssued::class, function (CompletionPayCodeIssued $event): bool {
        $payload = json_encode($event->payload, JSON_THROW_ON_ERROR);

        return ! str_contains($payload, 'provider_transaction')
            && ! str_contains($payload, 'authority_reference')
            && ! str_contains($payload, 'payer');
    });

    expect(fn () => $first->issuance->forceFill(['driver_version' => 'changed'])->save())
        ->toThrow(LogicException::class, 'immutable');
});

it('rejects completion issuance conflicts and issuer mismatches', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle($recognition, campaignCoverageTerms($recognition));
    $owner = $binding->standingFundingAddress->owner;
    $action = app(IssueCompletionPayCode::class);
    $action->handle($bound->coverage, $owner, new CompletionPayCodeInstructionsData(['name']));

    expect(fn () => $action->handle(
        $bound->coverage,
        $owner,
        new CompletionPayCodeInstructionsData(['name', 'mobile']),
    ))->toThrow(InvalidArgumentException::class, 'does not match')
        ->and(fn () => $action->handle(
            $bound->coverage,
            actingAsTestUser(),
            new CompletionPayCodeInstructionsData(['name']),
        ))->toThrow(InvalidArgumentException::class, 'must own')
        ->and(CompletionPayCodeIssuance::query()->count())->toBe(1);
});

it('claims a completion Pay Code through its non-financial execution driver', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle($recognition, campaignCoverageTerms($recognition));
    $issued = app(IssueCompletionPayCode::class)->handle(
        $bound->coverage,
        $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name', 'mobile']),
    );
    $before = campaignPaymentFinancialCounts();
    $walletValueBefore = (int) Transaction::query()->sum('amount');
    Event::fake([CompletionClaimEvidenceProjected::class]);

    $result = app(SubmitPayCodeClaim::class)->handle($issued->voucher, [
        'mobile' => '09173011987',
        'inputs' => ['name' => 'Completion Applicant', 'mobile' => '09173011987'],
    ]);
    $claim = $issued->voucher->claims()->sole();
    $projectionReplay = app(ProjectCompletionClaimEvidence::class)->handle($claim);
    $envelope = $bound->envelope->refresh();
    $publicPayload = json_encode($envelope->payload, JSON_THROW_ON_ERROR);

    expect($result->claimed)->toBeTrue()
        ->and($result->status)->toBe('redeemed')
        ->and($issued->voucher->refresh()->redeemed_at)->not->toBeNull()
        ->and(CompletionClaimEvidenceProjection::query()->count())->toBe(1)
        ->and($projectionReplay?->created)->toBeFalse()
        ->and($envelope->payload_version)->toBe(2)
        ->and(EnvelopePayloadVersion::query()->where('envelope_id', $envelope->getKey())->count())->toBe(2)
        ->and(data_get($envelope->payload, 'applicant.evidence.schema'))
        ->toBe('x-change.completion-claim-evidence-manifest.v1')
        ->and(data_get($envelope->payload, 'applicant.evidence.items.*.key'))
        ->toBe(['mobile', 'name'])
        ->and($publicPayload)->not->toContain('Completion Applicant')
        ->and($publicPayload)->not->toContain('09173011987')
        ->and($publicPayload)->not->toContain('artifact_path')
        ->and(AccountFundingReceipt::query()->count())->toBe($before['account_funding_receipts'])
        ->and(FundingSettlement::query()->count())->toBe($before['funding_settlements'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe($before['treasury_operations'])
        ->and((int) Transaction::query()->sum('amount'))->toBe($walletValueBefore);

    Event::assertDispatchedTimes(CompletionClaimEvidenceProjected::class, 1);
    Event::assertDispatched(CompletionClaimEvidenceProjected::class, function (CompletionClaimEvidenceProjected $event): bool {
        $payload = json_encode($event->payload, JSON_THROW_ON_ERROR);

        return ! str_contains($payload, 'Completion Applicant')
            && ! str_contains($payload, '09173011987')
            && ! str_contains($payload, 'voucher_claim_id');
    });

    $claim->evidence()->where('requirement_key', 'name')->sole()->forceFill(['status' => 'verified'])->save();
    expect(fn () => app(ProjectCompletionClaimEvidence::class)->handle($claim->fresh('evidence')))
        ->toThrow(InvalidArgumentException::class, 'does not match')
        ->and($envelope->refresh()->payload_version)->toBe(2);
});

it('projects only declared completion evidence from the compiled browser claim and replays safely', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle($recognition, auiCampaignCoverageTerms($recognition));
    $issued = app(IssueCompletionPayCode::class)->handle(
        $bound->coverage,
        $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name', 'mobile', 'email', 'address', 'birth_date'], requiresOtp: true),
    );
    $inputs = [
        'name' => 'Browser Applicant', 'mobile' => '09173011987',
        'email' => 'applicant@example.test', 'address' => 'Test address', 'birth_date' => '1990-01-01',
        'otp' => ['verified' => true], 'otp_verified' => true,
        '_step_name' => 'confirmation', 'account_number' => '', 'bank_code' => '',
        'completed_at' => now()->toIso8601String(), 'full_name' => 'Browser Applicant',
        'kyc' => [], 'splash_viewed' => true, 'viewed_at' => now()->toIso8601String(),
    ];
    $before = campaignPaymentFinancialCounts();
    Http::fake();
    Event::fake([CompletionClaimEvidenceProjected::class]);

    $result = app(SubmitCompiledFormClaim::class)->handle($issued->voucher, new PreparedCompiledClaimData(
        $issued->voucher->code, $issued->voucher->getKey(), $inputs,
    ));
    $claim = $issued->voucher->claims()->sole();
    $projection = CompletionClaimEvidenceProjection::query()->sole();
    $expected = ['address', 'birth_date', 'email', 'mobile', 'name', 'otp'];
    $prepared = app(PrepareCampaignPolicyCompletion::class)->handle($projection);

    expect($result->claimed)->toBeTrue()
        ->and($claim->evidence()->count())->toBeGreaterThan(6)
        ->and($claim->evidence()->where('requirement_key', 'splash_viewed')->exists())->toBeTrue()
        ->and(data_get($bound->envelope->refresh()->payload, 'applicant.evidence.items.*.key'))->toBe($expected)
        ->and($projection->source_snapshot['evidence_record_ids'])->toHaveCount(6)
        ->and(array_keys($prepared->privateApplicantEvidence()))->toBe($expected)
        ->and(campaignPaymentFinancialCounts())->toBe($before);
    Event::assertDispatched(CompletionClaimEvidenceProjected::class, fn ($event): bool => $event->payload['evidence_count'] === 6);

    $claim->evidence()->where('requirement_key', 'splash_viewed')->sole()->forceFill(['payload' => ['value' => false]])->save();
    expect(app(ProjectCompletionClaimEvidence::class)->handle($claim)?->created)->toBeFalse()
        ->and(CompletionClaimEvidenceProjection::query()->count())->toBe(1)
        ->and($bound->envelope->refresh()->payload_version)->toBe(2);
    Event::assertDispatchedTimes(CompletionClaimEvidenceProjected::class, 1);
    Http::assertNothingSent();
});

it('recovers a completed claim without re-executing it but rejects missing declared evidence', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle($recognition, campaignCoverageTerms($recognition));
    $issued = app(IssueCompletionPayCode::class)->handle(
        $bound->coverage, $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name']),
    );
    $claim = app(PrepareVoucherClaimEvidence::class)->handle($issued->voucher, ['inputs' => ['name' => 'Applicant', 'splash_viewed' => true]]);
    $meta = (array) $claim->meta;
    data_set($meta, 'evidence.execution_status', 'finalized');
    $claim->forceFill(['status' => 'redeemed', 'completed_at' => now(), 'meta' => $meta])->save();
    $evidence = $claim->evidence()->where('requirement_key', 'name')->sole();
    $evidence->forceFill(['requirement_key' => 'unexpected'])->save();
    expect(fn () => app(ProjectCompletionClaimEvidence::class)->handle($claim))
        ->toThrow(InvalidArgumentException::class, 'incomplete')
        ->and(CompletionClaimEvidenceProjection::query()->count())->toBe(0);
    $evidence->forceFill(['requirement_key' => 'name'])->save();
    $before = campaignPaymentFinancialCounts();
    expect(app(ProjectCompletionClaimEvidence::class)->handle($claim)?->created)->toBeTrue()
        ->and(app(ProjectCompletionClaimEvidence::class)->handle($claim)?->created)->toBeFalse()
        ->and(campaignPaymentFinancialCounts())->toBe($before);
});

it('rolls back the envelope version when completion evidence projection persistence fails', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle($recognition, campaignCoverageTerms($recognition));
    $issued = app(IssueCompletionPayCode::class)->handle(
        $bound->coverage,
        $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name']),
    );
    $claim = app(PrepareVoucherClaimEvidence::class)->handle($issued->voucher, [
        'inputs' => ['name' => 'Private Applicant'],
    ]);
    $meta = (array) $claim?->meta;
    data_set($meta, 'evidence.execution_status', 'finalized');
    $claim?->forceFill(['status' => 'redeemed', 'completed_at' => now(), 'meta' => $meta])->save();
    $auditCount = EnvelopeAuditLog::query()->where('envelope_id', $bound->envelope->getKey())->count();
    Event::listen('eloquent.creating: '.CompletionClaimEvidenceProjection::class, function (): never {
        throw new RuntimeException('forced evidence projection failure');
    });

    expect(fn () => app(ProjectCompletionClaimEvidence::class)->handle($claim->fresh('evidence')))
        ->toThrow(RuntimeException::class, 'forced evidence projection failure')
        ->and(CompletionClaimEvidenceProjection::query()->count())->toBe(0)
        ->and($bound->envelope->refresh()->payload_version)->toBe(1)
        ->and(EnvelopePayloadVersion::query()->where('envelope_id', $bound->envelope->getKey())->count())->toBe(1)
        ->and(EnvelopeAuditLog::query()->where('envelope_id', $bound->envelope->getKey())->count())->toBe($auditCount);
});

it('prepares the reserved AUI policy handoff without external or durable side effects', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle(
        $recognition,
        auiCampaignCoverageTerms($recognition),
    );
    $issued = app(IssueCompletionPayCode::class)->handle(
        $bound->coverage,
        $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name', 'mobile']),
    );
    app(SubmitPayCodeClaim::class)->handle($issued->voucher, [
        'mobile' => '09173011987',
        'inputs' => ['name' => 'Private AUI Applicant', 'mobile' => '09173011987'],
    ]);
    $projection = CompletionClaimEvidenceProjection::query()->sole();
    $before = campaignPaymentFinancialCounts();
    $envelopeVersionCount = EnvelopePayloadVersion::query()->count();
    Http::fake();
    Event::fake([
        CampaignPaymentRecognized::class,
        ProvisionalCoverageBound::class,
        CompletionPayCodeIssued::class,
        CompletionClaimEvidenceProjected::class,
    ]);

    $prepared = app(PrepareCampaignPolicyCompletion::class)->handle($projection);
    $replayed = app(PrepareCampaignPolicyCompletion::class)->handle($projection->fresh());
    $safePayload = json_encode($prepared->safeContext(), JSON_THROW_ON_ERROR);

    expect($prepared->driverId)->toBe(AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID)
        ->and($prepared->driverVersion)->toBe(AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION)
        ->and($prepared->idempotencyKey)->toBe('aui-policy-completion:'.$projection->reference)
        ->and($prepared->fingerprint)->toBe($replayed->fingerprint)
        ->and($prepared->privateApplicantEvidence())->toBe([
            'mobile' => '09173011987',
            'name' => 'Private AUI Applicant',
        ])
        ->and($safePayload)->not->toContain('Private AUI Applicant')
        ->and($safePayload)->not->toContain('09173011987')
        ->and($safePayload)->not->toContain('artifact_path')
        ->and(EnvelopePayloadVersion::query()->count())->toBe($envelopeVersionCount)
        ->and(AccountFundingReceipt::query()->count())->toBe($before['account_funding_receipts'])
        ->and(FundingSettlement::query()->count())->toBe($before['funding_settlements'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe($before['treasury_operations']);

    Http::assertNothingSent();
    Event::assertNothingDispatched();
});

it('fails closed for unavailable, duplicate, and mismatched policy completion drivers', function (): void {
    $driver = new AuiPersonalAccidentPolicyCompletionDriver;

    expect(fn () => (new CampaignPolicyCompletionDriverRegistry([]))->for(
        $driver->driverId(),
        $driver->driverVersion(),
    ))->toThrow(CampaignPolicyCompletionDriverUnavailable::class)
        ->and(fn () => new CampaignPolicyCompletionDriverRegistry([$driver, $driver]))
        ->toThrow(LogicException::class, 'Multiple campaign policy completion drivers');

    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle(
        $recognition,
        campaignCoverageTerms($recognition),
    );
    $issued = app(IssueCompletionPayCode::class)->handle(
        $bound->coverage,
        $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name']),
    );
    app(SubmitPayCodeClaim::class)->handle($issued->voucher, [
        'mobile' => '09173011987',
        'inputs' => ['name' => 'Not an AUI claim'],
    ]);

    expect(fn () => $driver->prepare(CompletionClaimEvidenceProjection::query()->sole()))
        ->toThrow(InvalidArgumentException::class, 'reserved driver identity');
});

it('governs durable policy completion requests and terminal outcomes without transport', function (): void {
    [$projection, $maker] = auiPolicyCompletionProjection();
    $checker = actingAsTestUser(0);
    $recorder = actingAsTestUser(0);
    $unauthorized = actingAsTestUser(0);
    $requestAction = app(RequestCampaignPolicyCompletion::class);

    expect(fn () => $requestAction->handle($projection, $maker, 'maker-authorization-1'))
        ->toThrow(AuthorizationException::class, 'maker authority');

    config()->set('x-change.settlement.policy_completion.maker_ids', [(string) $maker->getKey()]);
    config()->set('x-change.settlement.policy_completion.checker_ids', [(string) $checker->getKey()]);
    config()->set('x-change.settlement.policy_completion.outcome_recorder_ids', [(string) $recorder->getKey()]);
    $before = campaignPaymentFinancialCounts();
    $envelopeVersionCount = EnvelopePayloadVersion::query()->count();
    Http::fake();
    Event::fake([
        PolicyCompletionRequested::class,
        PolicyCompletionAuthorized::class,
        PolicyCompletionOutcomeRecorded::class,
    ]);

    $request = $requestAction->handle($projection, $maker, 'maker-authorization-1');
    $replayedRequest = $requestAction->handle($projection, $maker, 'maker-authorization-1');
    $encryptedPayload = DB::table('x_change_policy_completion_requests')
        ->where('id', $request->getKey())
        ->value('private_payload');

    expect($request->status)->toBe(PolicyCompletionRequestStatus::AwaitingApproval)
        ->and($replayedRequest->is($request))->toBeTrue()
        ->and((string) $encryptedPayload)->not->toContain('Private AUI Applicant')
        ->and((string) $encryptedPayload)->not->toContain('09173011987')
        ->and(fn () => $requestAction->handle($projection, $maker, 'changed-authorization'))
        ->toThrow(DomainException::class, 'does not match')
        ->and(fn () => app(ApproveCampaignPolicyCompletion::class)->handle(
            $request,
            $unauthorized,
            'checker-approval-1',
        ))->toThrow(AuthorizationException::class, 'checker authority')
        ->and(fn () => app(RecordCampaignPolicyCompletionOutcome::class)->handle(
            $request,
            $recorder,
            new PolicyCompletionOutcomeData(PolicyCompletionOutcomeStatus::Succeeded, 'accepted'),
        ))->toThrow(DomainException::class, 'authorized');

    expect(fn () => app(RecordCampaignPolicyCompletionOutcome::class)->handle(
        $request,
        $recorder,
        new PolicyCompletionOutcomeData(
            PolicyCompletionOutcomeStatus::Failed,
            'declined',
            safeResult: ['applicant_name' => 'Must not be public'],
        ),
    ))->toThrow(DomainException::class, 'is not allowed');

    config()->set('x-change.settlement.policy_completion.checker_ids', [
        (string) $checker->getKey(),
        (string) $maker->getKey(),
    ]);
    expect(fn () => app(ApproveCampaignPolicyCompletion::class)->handle(
        $request,
        $maker,
        'self-approval',
    ))->toThrow(DomainException::class, 'independent');

    $approved = app(ApproveCampaignPolicyCompletion::class)->handle(
        $request,
        $checker,
        'checker-approval-1',
    );
    $approvalReplay = app(ApproveCampaignPolicyCompletion::class)->handle(
        $request,
        $checker,
        'checker-approval-1',
    );
    $outcomeData = new PolicyCompletionOutcomeData(
        status: PolicyCompletionOutcomeStatus::Succeeded,
        resultCode: 'accepted',
        providerReference: 'provider-result-1',
        safeResult: ['document_ready' => true],
        privateResult: ['private_receipt' => 'sensitive-result'],
    );
    $outcome = app(RecordCampaignPolicyCompletionOutcome::class)->handle(
        $approved,
        $recorder,
        $outcomeData,
    );
    $outcomeReplay = app(RecordCampaignPolicyCompletionOutcome::class)->handle(
        $approved,
        $recorder,
        $outcomeData,
    );
    $encryptedResult = DB::table('x_change_policy_completion_outcomes')
        ->where('id', $outcome->getKey())
        ->value('private_result');

    expect($approved->status)->toBe(PolicyCompletionRequestStatus::Authorized)
        ->and($approvalReplay->status)->toBe(PolicyCompletionRequestStatus::Authorized)
        ->and($outcome->status)->toBe(PolicyCompletionOutcomeStatus::Succeeded)
        ->and($outcomeReplay->is($outcome))->toBeTrue()
        ->and($request->refresh()->status)->toBe(PolicyCompletionRequestStatus::Succeeded)
        ->and((string) $encryptedResult)->not->toContain('sensitive-result')
        ->and(fn () => app(RecordCampaignPolicyCompletionOutcome::class)->handle(
            $request,
            $recorder,
            new PolicyCompletionOutcomeData(PolicyCompletionOutcomeStatus::Failed, 'declined'),
        ))->toThrow(DomainException::class, 'does not match')
        ->and(PolicyCompletionRequest::query()->count())->toBe(1)
        ->and(PolicyCompletionOutcome::query()->count())->toBe(1)
        ->and(EnvelopePayloadVersion::query()->count())->toBe($envelopeVersionCount)
        ->and(AccountFundingReceipt::query()->count())->toBe($before['account_funding_receipts'])
        ->and(FundingSettlement::query()->count())->toBe($before['funding_settlements'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe($before['treasury_operations']);

    Event::assertDispatchedTimes(PolicyCompletionRequested::class, 1);
    Event::assertDispatchedTimes(PolicyCompletionAuthorized::class, 1);
    Event::assertDispatchedTimes(PolicyCompletionOutcomeRecorded::class, 1);
    Event::assertDispatched(PolicyCompletionRequested::class, function (PolicyCompletionRequested $event): bool {
        $payload = json_encode($event->payload, JSON_THROW_ON_ERROR);

        return ! str_contains($payload, 'Private AUI Applicant')
            && ! str_contains($payload, '09173011987');
    });
    Event::assertDispatched(PolicyCompletionOutcomeRecorded::class, function (PolicyCompletionOutcomeRecorded $event): bool {
        return ! str_contains(
            json_encode($event->payload, JSON_THROW_ON_ERROR),
            'sensitive-result',
        );
    });
    Http::assertNothingSent();
});

it('projects an owner scoped and redacted campaign policy lifecycle without side effects', function (): void {
    [$projection, $owner] = auiPolicyCompletionProjection();
    $otherOwner = actingAsTestUser(0);
    $before = campaignPaymentFinancialCounts();
    $modelCounts = [
        'requests' => PolicyCompletionRequest::query()->count(),
        'outcomes' => PolicyCompletionOutcome::query()->count(),
        'payload_versions' => EnvelopePayloadVersion::query()->count(),
    ];
    Http::fake();
    Event::fake([
        CampaignPaymentRecognized::class,
        ProvisionalCoverageBound::class,
        CompletionPayCodeIssued::class,
        CompletionClaimEvidenceProjected::class,
        PolicyCompletionRequested::class,
        PolicyCompletionAuthorized::class,
        PolicyCompletionOutcomeRecorded::class,
    ]);

    $rows = app(CampaignPolicyLifecycleReadModel::class)->forOwner($owner);
    $otherRows = app(CampaignPolicyLifecycleReadModel::class)->forOwner($otherOwner);
    $row = $rows[0]->toSafeArray();
    $serialized = json_encode($row, JSON_THROW_ON_ERROR);

    expect($rows)->toHaveCount(1)
        ->and($otherRows)->toBe([])
        ->and($row['schema'])->toBe('x-change.campaign-policy-lifecycle.v1')
        ->and($row['stage'])->toBe('claim_evidence_ready')
        ->and($row['attention_required'])->toBeFalse()
        ->and($row['completion']['projection_reference'])->toBe($projection->reference)
        ->and($row['completion']['claim_number'])->toBe($projection->claim->claim_number)
        ->and($row['policy'])->toBeNull()
        ->and($serialized)->not->toContain('Private AUI Applicant')
        ->and($serialized)->not->toContain('09173011987')
        ->and($serialized)->not->toContain('provider_transaction_key')
        ->and($serialized)->not->toContain('authorization_reference')
        ->and($serialized)->not->toContain('source_snapshot')
        ->and(PolicyCompletionRequest::query()->count())->toBe($modelCounts['requests'])
        ->and(PolicyCompletionOutcome::query()->count())->toBe($modelCounts['outcomes'])
        ->and(EnvelopePayloadVersion::query()->count())->toBe($modelCounts['payload_versions'])
        ->and(AccountFundingReceipt::query()->count())->toBe($before['account_funding_receipts'])
        ->and(FundingSettlement::query()->count())->toBe($before['funding_settlements'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe($before['treasury_operations']);

    Http::assertNothingSent();
    Event::assertNothingDispatched();
});

it('serves the authenticated owner policy lifecycle as a read only cockpit page', function (): void {
    [$projection, $owner] = auiPolicyCompletionProjection();
    $before = campaignPaymentFinancialCounts();
    Http::preventStrayRequests();

    $response = $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.policy-lifecycle.index'));

    $response->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/CampaignPolicyLifecycle')
        ->assertJsonCount(1, 'props.lifecycles')
        ->assertJsonPath('props.lifecycles.0.schema', 'x-change.campaign-policy-lifecycle.v1')
        ->assertJsonPath('props.lifecycles.0.stage', 'claim_evidence_ready')
        ->assertJsonPath('props.lifecycles.0.completion.projection_reference', $projection->reference)
        ->assertJsonMissingPath('props.lifecycles.0.payment.provider_transaction_key')
        ->assertJsonMissingPath('props.lifecycles.0.policy.authorization_reference');

    $serialized = $response->getContent();
    expect($serialized)->not->toContain('Private AUI Applicant')
        ->and($serialized)->not->toContain('09173011987')
        ->and(AccountFundingReceipt::query()->count())->toBe($before['account_funding_receipts'])
        ->and(FundingSettlement::query()->count())->toBe($before['funding_settlements'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe($before['treasury_operations']);

    Http::assertNothingSent();
});

it('keeps completion Pay Code navigation owner scoped', function (): void {
    [$projection, $owner] = auiPolicyCompletionProjection();
    $payCode = $projection->issuance->voucher->code;

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $payCode]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/VoucherDetail');

    $otherOwner = actingAsTestUser(0);

    $this->actingAs($otherOwner)
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => $payCode]))
        ->assertNotFound();
});

it('keeps the campaign policy lifecycle behind cockpit authentication', function (): void {
    $route = app('router')->getRoutes()
        ->getByName('x-change.cockpit.campaigns.policy-lifecycle.index');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('auth');
});

it('resolves every campaign policy lifecycle stage with explicit attention semantics', function (
    bool $issuance,
    bool $projection,
    ?PolicyCompletionRequestStatus $request,
    ?PolicyCompletionOutcomeStatus $outcome,
    string $expectedStage,
    bool $attentionRequired,
): void {
    expect(app(CampaignPolicyLifecycleStageResolver::class)->resolve(
        $issuance,
        $projection,
        $request,
        $outcome,
    ))->toBe([
        'stage' => $expectedStage,
        'attention_required' => $attentionRequired,
    ]);
})->with([
    'coverage' => [false, false, null, null, 'provisional_coverage_active', false],
    'awaiting claim' => [true, false, null, null, 'awaiting_completion_claim', false],
    'evidence ready' => [true, true, null, null, 'claim_evidence_ready', false],
    'awaiting approval' => [true, true, PolicyCompletionRequestStatus::AwaitingApproval, null, 'policy_awaiting_approval', false],
    'authorized' => [true, true, PolicyCompletionRequestStatus::Authorized, null, 'policy_authorized', false],
    'succeeded' => [true, true, PolicyCompletionRequestStatus::Succeeded, PolicyCompletionOutcomeStatus::Succeeded, 'policy_succeeded', false],
    'failed' => [true, true, PolicyCompletionRequestStatus::Failed, PolicyCompletionOutcomeStatus::Failed, 'policy_failed', true],
    'indeterminate' => [true, true, PolicyCompletionRequestStatus::Indeterminate, PolicyCompletionOutcomeStatus::Indeterminate, 'policy_indeterminate', true],
]);

it('rolls back terminal policy state when outcome persistence fails', function (): void {
    [$projection, $maker] = auiPolicyCompletionProjection();
    $checker = actingAsTestUser(0);
    $recorder = actingAsTestUser(0);
    config()->set('x-change.settlement.policy_completion.maker_ids', [(string) $maker->getKey()]);
    config()->set('x-change.settlement.policy_completion.checker_ids', [(string) $checker->getKey()]);
    config()->set('x-change.settlement.policy_completion.outcome_recorder_ids', [(string) $recorder->getKey()]);
    $request = app(RequestCampaignPolicyCompletion::class)->handle(
        $projection,
        $maker,
        'maker-authorization-rollback',
    );
    $approved = app(ApproveCampaignPolicyCompletion::class)->handle(
        $request,
        $checker,
        'checker-approval-rollback',
    );
    Event::listen('eloquent.creating: '.PolicyCompletionOutcome::class, function (): never {
        throw new RuntimeException('forced policy outcome failure');
    });

    expect(fn () => app(RecordCampaignPolicyCompletionOutcome::class)->handle(
        $approved,
        $recorder,
        new PolicyCompletionOutcomeData(PolicyCompletionOutcomeStatus::Succeeded, 'accepted'),
    ))->toThrow(RuntimeException::class, 'forced policy outcome failure')
        ->and($request->refresh()->status)->toBe(PolicyCompletionRequestStatus::Authorized)
        ->and(PolicyCompletionOutcome::query()->count())->toBe(0);
});

it('keeps policy completion transport disabled until an exact accepted disposition is ready', function (): void {
    [$projection, $maker] = auiPolicyCompletionProjection();
    $checker = actingAsTestUser(0);
    config()->set('x-change.settlement.policy_completion.maker_ids', [(string) $maker->getKey()]);
    config()->set('x-change.settlement.policy_completion.checker_ids', [(string) $checker->getKey()]);
    $request = app(RequestCampaignPolicyCompletion::class)->handle(
        $projection,
        $maker,
        'maker-transport-readiness',
    );
    $check = app(CheckCampaignPolicyCompletionTransportReadiness::class);
    $before = campaignPaymentFinancialCounts();
    $envelopeVersionCount = EnvelopePayloadVersion::query()->count();
    Http::fake();

    $notAuthorized = $check->handle($request);
    expect($notAuthorized->ready)->toBeFalse()
        ->and($notAuthorized->status)->toBe('not_authorized');

    $approved = app(ApproveCampaignPolicyCompletion::class)->handle(
        $request,
        $checker,
        'checker-transport-readiness',
    );
    $notConfigured = $check->handle($approved);

    expect($notConfigured->ready)->toBeFalse()
        ->and($notConfigured->status)->toBe('not_configured')
        ->and($notConfigured->missingFields)->toContain('credential_reference')
        ->and($notConfigured->dispositionFingerprint)->toBeNull();

    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => [
            'enabled' => true,
            'contract_id' => 'aui-policy-completion',
            'contract_version' => '2026-09-23',
        ],
    ]);
    $incomplete = $check->handle($approved);

    expect($incomplete->ready)->toBeFalse()
        ->and($incomplete->status)->toBe('invalid')
        ->and($incomplete->missingFields)->toBe([]);

    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => policyCompletionTransportDisposition(),
    ]);
    $credentialsUnavailable = $check->handle($approved);

    expect($credentialsUnavailable->ready)->toBeFalse()
        ->and($credentialsUnavailable->status)->toBe('credentials_unavailable');

    config()->set('services.aui.policy_completion_token', 'secret-policy-api-token');
    $ready = $check->handle($approved);
    $replayed = $check->handle($approved);
    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => array_reverse(
            policyCompletionTransportDisposition(),
            true,
        ),
    ]);
    $reordered = $check->handle($approved);
    $safePayload = json_encode($ready->toSafeArray(), JSON_THROW_ON_ERROR);

    expect($ready->ready)->toBeTrue()
        ->and($ready->status)->toBe('ready')
        ->and($ready->dispositionFingerprint)->toMatch('/^[a-f0-9]{64}$/')
        ->and($replayed->dispositionFingerprint)->toBe($ready->dispositionFingerprint)
        ->and($reordered->dispositionFingerprint)->toBe($ready->dispositionFingerprint)
        ->and($safePayload)->not->toContain('secret-policy-api-token')
        ->and($safePayload)->not->toContain('services.aui.policy_completion_token')
        ->and($request->refresh()->status)->toBe(PolicyCompletionRequestStatus::Authorized)
        ->and(PolicyCompletionOutcome::query()->count())->toBe(0)
        ->and(EnvelopePayloadVersion::query()->count())->toBe($envelopeVersionCount)
        ->and(AccountFundingReceipt::query()->count())->toBe($before['account_funding_receipts'])
        ->and(FundingSettlement::query()->count())->toBe($before['funding_settlements'])
        ->and(TreasuryInventoryOperation::query()->count())->toBe($before['treasury_operations']);

    Http::assertNothingSent();
});

it('requires a transport disposition for the exact completion driver version', function (): void {
    [$projection, $maker] = auiPolicyCompletionProjection();
    $checker = actingAsTestUser(0);
    config()->set('x-change.settlement.policy_completion.maker_ids', [(string) $maker->getKey()]);
    config()->set('x-change.settlement.policy_completion.checker_ids', [(string) $checker->getKey()]);
    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@9.9.9' => policyCompletionTransportDisposition(),
    ]);
    $request = app(RequestCampaignPolicyCompletion::class)->handle(
        $projection,
        $maker,
        'maker-exact-transport',
    );
    $approved = app(ApproveCampaignPolicyCompletion::class)->handle(
        $request,
        $checker,
        'checker-exact-transport',
    );
    Http::fake();

    $readiness = app(CheckCampaignPolicyCompletionTransportReadiness::class)->handle($approved);

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->status)->toBe('not_configured');

    $manifestWithSecret = [
        ...policyCompletionTransportDisposition(),
        'credential_value' => 'must-never-be-accepted',
    ];
    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => $manifestWithSecret,
    ]);
    $invalid = app(CheckCampaignPolicyCompletionTransportReadiness::class)->handle($approved);

    expect($invalid->ready)->toBeFalse()
        ->and($invalid->status)->toBe('invalid')
        ->and(json_encode($invalid->toSafeArray(), JSON_THROW_ON_ERROR))
        ->not->toContain('must-never-be-accepted');

    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => [
            ...policyCompletionTransportDisposition(),
            'accepted' => false,
        ],
    ]);
    expect(app(CheckCampaignPolicyCompletionTransportReadiness::class)->handle($approved)->status)
        ->toBe('invalid');

    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => [
            ...policyCompletionTransportDisposition(),
            'driver_version' => '9.9.9',
        ],
    ]);
    expect(app(CheckCampaignPolicyCompletionTransportReadiness::class)->handle($approved)->status)
        ->toBe('invalid');

    config()->set('x-change.settlement.policy_completion.transports', [
        AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID.'@'.AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION => [
            ...policyCompletionTransportDisposition(),
            'submission_endpoint' => 'http://policy-provider.example.test/v1/completions',
        ],
    ]);
    expect(app(CheckCampaignPolicyCompletionTransportReadiness::class)->handle($approved)->status)
        ->toBe('invalid');
    Http::assertNothingSent();
});

it('rolls back the completion voucher when immutable link persistence fails', function (): void {
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle($recognition, campaignCoverageTerms($recognition));
    $voucherCount = Voucher::query()->count();
    Event::listen('eloquent.creating: '.CompletionPayCodeIssuance::class, function (): never {
        throw new RuntimeException('forced completion link failure');
    });

    expect(fn () => app(IssueCompletionPayCode::class)->handle(
        $bound->coverage,
        $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name']),
    ))->toThrow(RuntimeException::class, 'forced completion link failure')
        ->and(CompletionPayCodeIssuance::query()->count())->toBe(0)
        ->and(Voucher::query()->count())->toBe($voucherCount);
});

it('queues the registered campaign payment continuation only after recognition commits', function (): void {
    Queue::fake();

    DB::beginTransaction();
    [$recognition] = recognizedCampaignPayment(5_000);
    Queue::assertNothingPushed();
    DB::commit();

    Queue::assertPushed(AdvanceCampaignPaymentLifecycleJob::class, fn ($job): bool => $job->recognitionReference === $recognition->reference && $job->queue === 'x-change-funding');
    Queue::assertPushed(AdvanceCampaignPaymentLifecycleJob::class, 1);
});

it('does not enqueue campaign lifecycle work for a rolled back recognition', function (): void {
    Queue::fake();
    DB::beginTransaction();
    recognizedCampaignPayment(5_000);
    DB::rollBack();

    Queue::assertNothingPushed();
    expect(CampaignPaymentRecognition::query()->count())->toBe(0);
});

it('advances a recognized campaign payment once across duplicate job deliveries', function (): void {
    Queue::fake();
    [$recognition] = auiRecognizedPaymentForContinuation();
    $before = campaignPaymentFinancialCounts();
    $job = new AdvanceCampaignPaymentLifecycleJob($recognition->reference);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    $coverage = ProvisionalCoverage::query()->sole();
    $issuance = CompletionPayCodeIssuance::query()->with('voucher')->sole();
    expect(Envelope::query()->count())->toBe(1)
        ->and($coverage->coverage_amount_minor)->toBe(500_000)
        ->and($coverage->effective_at->equalTo($recognition->settled_at))->toBeTrue()
        ->and($coverage->expires_at->equalTo($recognition->settled_at->addHours(24)))->toBeTrue()
        ->and($issuance->voucher->code)->toStartWith('POLI')
        ->and(data_get($issuance->requirements_snapshot, 'requires_otp'))->toBeTrue()
        ->and(campaignPaymentFinancialCounts())->toMatchArray([
            'account_funding_receipts' => $before['account_funding_receipts'],
            'funding_settlements' => $before['funding_settlements'],
            'treasury_operations' => $before['treasury_operations'],
            'vouchers' => $before['vouchers'] + 1,
        ]);
    Http::assertNothingSent();
});

it('resumes completion issuance after a failure following durable coverage creation', function (): void {
    Queue::fake();
    [$recognition] = auiRecognizedPaymentForContinuation();
    $realIssuer = app(PayCodeIssuanceContract::class);
    $issuer = Mockery::mock(PayCodeIssuanceContract::class);
    $issuer->shouldReceive('issue')->once()->andThrow(new RuntimeException('temporary issuance failure'));
    app()->instance(PayCodeIssuanceContract::class, $issuer);
    $job = new AdvanceCampaignPaymentLifecycleJob($recognition->reference);

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(RuntimeException::class, 'temporary issuance failure');
    expect(ProvisionalCoverage::query()->count())->toBe(1)
        ->and(CompletionPayCodeIssuance::query()->count())->toBe(0);

    app()->instance(PayCodeIssuanceContract::class, $realIssuer);
    app()->call([$job, 'handle']);
    expect(ProvisionalCoverage::query()->count())->toBe(1)
        ->and(CompletionPayCodeIssuance::query()->count())->toBe(1)
        ->and(Envelope::query()->count())->toBe(1);
});

it('leaves campaigns without a qualified coverage driver unchanged', function (): void {
    Queue::fake();
    [$recognition] = recognizedCampaignPayment(5_000);
    $before = campaignPaymentFinancialCounts();
    app()->call([new AdvanceCampaignPaymentLifecycleJob($recognition->reference), 'handle']);

    expect(ProvisionalCoverage::query()->count())->toBe(0)
        ->and(CompletionPayCodeIssuance::query()->count())->toBe(0)
        ->and(campaignPaymentFinancialCounts())->toBe($before);
});

it('inspects an exact recognized payment and dispatches recovery only when requested', function (): void {
    Queue::fake();
    [$recognition] = recognizedCampaignPayment(5_000);
    Queue::fake();

    $this->artisan('x-change:campaigns:resume-payment', ['recognition' => $recognition->reference])
        ->assertSuccessful();
    Queue::assertNothingPushed();
    $this->artisan('x-change:campaigns:resume-payment', ['recognition' => $recognition->reference, '--dispatch' => true])
        ->assertSuccessful();
    Queue::assertPushed(AdvanceCampaignPaymentLifecycleJob::class, fn ($job): bool => $job->recognitionReference === $recognition->reference);
    $this->artisan('x-change:campaigns:resume-payment', ['recognition' => 'missing', '--dispatch' => true])
        ->assertFailed();
    Queue::assertPushed(AdvanceCampaignPaymentLifecycleJob::class, 1);
});

it('queues one durable completion SMS for wallet source accounts across lifecycle replays', function (string $institution, string $account): void {
    Queue::fake();
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    [$recognition] = auiRecognizedPaymentForContinuation([
        'payer_institution_ciphertext' => $institution,
        'payer_account_ciphertext' => $account,
    ]);
    $job = new AdvanceCampaignPaymentLifecycleJob($recognition->reference);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    $record = FeedbackDeliveryRecord::query()->sole();
    expect($record->status)->toBe('queued')
        ->and($record->attempt_count)->toBe(1)
        ->and($recognition->canonicalObservation->payer_identity_provider_verified)->toBeFalse();
    $delivery = app(FeedbackDeliveryAttemptRecorderContract::class)
        ->forCorrelation('campaign-payment-completion:'.$recognition->reference)[0];
    expect($delivery->recipient->phone)->toBe('639173011987');
    Queue::assertPushed(DeliverQueuedFeedbackSmsJob::class,
        fn ($sms): bool => $sms->deliveryId === $record->delivery_id
            && str_contains($sms->message, '/x/claim/POLI')
            && str_contains($sms->message, 'demonstration only'));
    Http::assertNothingSent();
})->with([
    ['GXCHPHM2XXX', '09173011987'],
    ['PAPHPHM1XXX', '639173011987'],
    ['PAPHPHM1XXX', '+639173011987'],
]);

it('sends the campaign completion link through the feedback worker once after provider acceptance', function (): void {
    Queue::fake();
    [$recognition] = auiRecognizedPaymentForContinuation([
        'payer_institution_ciphertext' => 'GXCHPHM2XXX',
        'payer_account_ciphertext' => '09173011987',
    ]);
    $job = new AdvanceCampaignPaymentLifecycleJob($recognition->reference);
    app()->call([$job, 'handle']);
    $sms = Queue::pushed(DeliverQueuedFeedbackSmsJob::class)->first();
    $provider = Mockery::mock(EngageSpark::class);
    $provider->shouldReceive('getOrgId')->once()->andReturn('test-org');
    $provider->shouldReceive('send')->once()
        ->with(Mockery::on(fn (array $payload): bool => $payload['to'] === '639173011987'
            && str_contains($payload['message'], '/x/claim/POLI')),
            ServiceMode::SMS)
        ->andReturn(['message_id' => 'test-campaign-sms', 'status' => 'ACCEPTED']);
    app()->instance(EngageSpark::class, $provider);
    app()->call([$sms, 'handle']);
    app()->call([$sms, 'handle']);
    app()->call([$job, 'handle']);

    expect(FeedbackDeliveryRecord::query()->sole()->status)->toBe('sent');
    Queue::assertPushed(DeliverQueuedFeedbackSmsJob::class, 1);
});

it('does not route completion SMS to unsupported or invalid source accounts', function (string $institution, string $account): void {
    Queue::fake();
    [$recognition] = auiRecognizedPaymentForContinuation([
        'payer_institution_ciphertext' => $institution,
        'payer_account_ciphertext' => $account,
    ]);
    app()->call([new AdvanceCampaignPaymentLifecycleJob($recognition->reference), 'handle']);
    expect(FeedbackDeliveryRecord::query()->count())->toBe(0);
    Queue::assertNotPushed(DeliverQueuedFeedbackSmsJob::class);
    Http::assertNothingSent();
})->with([
    ['MYDBPHM2XXX', '09173011987'],
    ['UNKNOWN', '09173011987'],
    ['PAPHPHM1XXX', '12345'],
    ['GXCHPHM2XXX', ''],
    ['GXCHPHM2XXX', 'abc09173011987'],
]);

it('refuses an inline SMS driver for campaign completion delivery', function (): void {
    Queue::fake();
    [$recognition] = auiRecognizedPaymentForContinuation([
        'payer_institution_ciphertext' => 'PAPHPHM1XXX',
        'payer_account_ciphertext' => '09173011987',
    ]);
    $registry = Mockery::mock(FeedbackChannelRegistryContract::class);
    $registry->shouldReceive('driver')->with('sms')->once()
        ->andReturn(Mockery::mock(FeedbackChannelDriverContract::class));
    app()->instance(FeedbackChannelRegistryContract::class, $registry);

    expect(fn () => app()->call([new AdvanceCampaignPaymentLifecycleJob($recognition->reference), 'handle']))
        ->toThrow(RuntimeException::class, 'Campaign completion SMS requires the queued SMS driver.');
    expect(FeedbackDeliveryRecord::query()->count())->toBe(0);
    Http::assertNothingSent();
});

/** @return array{CampaignPaymentRecognition, CampaignPaymentQrBinding} */
function auiRecognizedPaymentForContinuation(array $payer = []): array
{
    configureCampaignCoverageTestDriver();
    Http::fake();
    [$recognition, $binding] = recognizedCampaignPayment(5_000, $payer);
    $recognition->campaignRecord()->forceFill(['settings' => [
        'entry_mode' => CampaignEntryMode::ReusablePaymentQr->value,
        'scenario_run' => [
            'reference' => 'RUN-AUI-CONTINUATION',
            'scenario' => 'aui_on_demand_insurance_payment',
            'envelope_driver_id' => AuiPersonalAccidentCampaignCoverageDriver::DRIVER_ID,
            'envelope_driver_version' => AuiPersonalAccidentCampaignCoverageDriver::DRIVER_VERSION,
            'product' => [
                'name' => 'Cubao to Lucena Personal Accident Plan',
                'premium_minor' => 5_000,
                'insured_amount_minor' => 500_000,
                'currency' => 'PHP',
                'coverage_duration_hours' => 24,
            ],
        ],
    ]])->save();

    return [$recognition, $binding];
}

function campaignPaymentQrOwner(): User
{
    $owner = actingAsTestUser();
    $owner->forceFill(['name' => 'AUI Insurance'])->save();

    return $owner;
}

function configureCampaignCoverageTestDriver(): void
{
    config()->set('filesystems.disks.envelope-drivers', [
        'driver' => 'local',
        'root' => dirname(__DIR__, 3).'/Fixtures/envelope-drivers',
        'throw' => true,
    ]);
    config()->set('settlement-envelope.driver_disk', 'envelope-drivers');
    Storage::forgetDisk('envelope-drivers');
    app()->forgetInstance(DriverService::class);
    app()->forgetInstance(EnvelopeService::class);
}

/** @return array{CampaignPaymentRecognition, CampaignPaymentQrBinding} */
function recognizedCampaignPayment(int $amountMinor = 12_200, array $payer = []): array
{
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: $amountMinor,
    );
    $result = app(RecognizeQualifyingCampaignPayment::class)->handle(
        $binding,
        campaignPaymentObservation($address, 'transaction-coverage-'.str()->ulid(), 'settled', $amountMinor, $payer),
    );

    return [$result->recognition, $binding];
}

function campaignCoverageTerms(
    CampaignPaymentRecognition $recognition,
    array $terms = ['plan' => 'test-coverage'],
): ProvisionalCoverageTermsData {
    return new ProvisionalCoverageTermsData(
        driverId: 'campaign-provisional-coverage',
        driverVersion: '1.0.0',
        coverageType: 'provisional-service-coverage',
        currency: 'PHP',
        effectiveAt: $recognition->settled_at,
        expiresAt: $recognition->settled_at->addDay(),
        coverageAmountMinor: $recognition->gross_amount_minor,
        terms: $terms,
        authorization: [
            'authority' => 'campaign-driver',
            'authority_reference' => 'test-authorization',
        ],
    );
}

function auiCampaignCoverageTerms(
    CampaignPaymentRecognition $recognition,
): ProvisionalCoverageTermsData {
    return new ProvisionalCoverageTermsData(
        driverId: AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
        driverVersion: AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
        coverageType: 'personal-accident-provisional-cover',
        currency: 'PHP',
        effectiveAt: $recognition->settled_at,
        expiresAt: $recognition->settled_at->addDay(),
        coverageAmountMinor: $recognition->gross_amount_minor,
        terms: ['plan' => 'aui-on-demand-personal-accident'],
        authorization: [
            'authority' => 'campaign-driver',
            'authority_reference' => 'aui-test-authorization',
        ],
    );
}

/** @return array{CompletionClaimEvidenceProjection, User} */
function auiPolicyCompletionProjection(): array
{
    configureCampaignCoverageTestDriver();
    [$recognition, $binding] = recognizedCampaignPayment();
    $bound = app(BindProvisionalCoverage::class)->handle(
        $recognition,
        auiCampaignCoverageTerms($recognition),
    );
    $issued = app(IssueCompletionPayCode::class)->handle(
        $bound->coverage,
        $binding->standingFundingAddress->owner,
        new CompletionPayCodeInstructionsData(['name', 'mobile']),
    );
    app(SubmitPayCodeClaim::class)->handle($issued->voucher, [
        'mobile' => '09173011987',
        'inputs' => ['name' => 'Private AUI Applicant', 'mobile' => '09173011987'],
    ]);

    return [
        CompletionClaimEvidenceProjection::query()->sole(),
        $binding->standingFundingAddress->owner,
    ];
}

/** @return array<string, bool|int|string> */
function policyCompletionTransportDisposition(): array
{
    return [
        'schema' => 'x-change.policy-completion-transport-disposition.v1',
        'enabled' => true,
        'accepted' => true,
        'driver_id' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
        'driver_version' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
        'provider' => 'synthetic-test-provider',
        'contract_id' => 'aui-policy-completion',
        'contract_version' => '2026-09-23',
        'submission_endpoint' => 'https://policy-provider.example.test/v1/completions',
        'authentication_scheme' => 'bearer-token',
        'request_schema_reference' => 'test://policy-completion-request',
        'request_schema_version' => '1.0',
        'request_schema_digest' => 'sha256:'.str_repeat('a', 64),
        'response_schema_reference' => 'test://policy-completion-response',
        'response_schema_version' => '1.0',
        'response_schema_digest' => 'sha256:'.str_repeat('b', 64),
        'idempotency_mechanism' => 'provider-request-key',
        'connect_timeout_seconds' => 5,
        'response_timeout_seconds' => 15,
        'retry_policy' => 'selective-transient-only',
        'ambiguous_outcome_policy' => 'record-indeterminate-and-reconcile',
        'reconciliation_mode' => 'provider-status-query',
        'credential_reference' => 'services.aui.policy_completion_token',
        'acceptance_reference' => 'synthetic-test-acceptance',
        'accepted_at' => '2026-09-23T00:00:00+00:00',
        'accepted_by_reference' => 'test-architecture-authority',
    ];
}

function campaignCoverageOrchestrator(
    FakeCampaignCoverageDriver $driver,
): OrchestrateProvisionalCoverage {
    return new OrchestrateProvisionalCoverage(
        drivers: new CampaignCoverageDriverRegistry([$driver]),
        bind: app(BindProvisionalCoverage::class),
    );
}

function campaignPaymentQrCampaign(User $owner, CampaignEntryMode $entryMode): LeadCampaign
{
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'name' => 'Campaign payment QR template '.str()->ulid(),
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => [
            'cash' => ['amount' => 0, 'currency' => 'PHP'],
            'count' => 1,
            'prefix' => 'AUI',
            'mask' => '****',
        ],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);

    return app(CreateLeadCampaign::class)->handle($owner, $template, [
        'title' => 'AUI payment campaign '.str()->ulid(),
        'settings' => ['entry_mode' => $entryMode->value],
    ]);
}

/**
 * @return array{StandingFundingAddress, StandingFundingQrArtifact}
 */
function campaignPaymentQrAddress(
    User $owner,
    FundingAddressPurpose $purpose = FundingAddressPurpose::Payment,
): array {
    $addressValue = '91500'.str_pad((string) random_int(1, 9999999999999999), 16, '0', STR_PAD_LEFT);
    $address = StandingFundingAddress::query()->create([
        'binding_key' => hash('sha256', 'campaign-payment-qr-binding-'.$addressValue),
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'account_reference' => 'campaign:'.$owner->getKey(),
        'provider_code' => 'netbank',
        'purpose' => $purpose,
        'recognition_mode' => FundingRecognitionMode::ObserveOnly,
        'status' => FundingAddressStatus::Active,
        'version' => 1,
        'provider_reference' => 'standing:netbank:'.$addressValue,
        'funding_address_ciphertext' => $addressValue,
        'funding_address_hash' => hash('sha256', $addressValue),
        'currency' => 'PHP',
        'activated_at' => now(),
        'metadata' => ['reusable' => true],
    ]);
    $artifact = $address->qrArtifacts()->create([
        'status' => 'active',
        'version' => 1,
        'artifact_fingerprint' => hash('sha256', 'artifact-'.$addressValue),
        'mime_type' => 'image/png',
        'qr_mode' => 'Static',
        'transaction_type' => 'P2M',
        'embedded_amount' => false,
        'provider_generated' => true,
        'payload_ciphertext' => base64_encode('campaign-payment-qr'),
        'display_snapshot_ciphertext' => ['provider' => 'netbank'],
        'generated_at' => now(),
    ]);

    return [$address, $artifact];
}

function campaignPaymentObservation(
    StandingFundingAddress $address,
    string $transactionId,
    string $status,
    int $amountMinor = 12_200,
    array $payer = [],
): ProviderFundingObservation {
    $occurredAt = now()->addMinute()->toImmutable();

    return ProviderFundingObservation::query()->create([
        ...$payer,
        'observation_key' => hash('sha256', $transactionId.'-'.$status),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => 'operation-'.$transactionId,
        'funding_address' => 'sha256:'.$address->funding_address_hash,
        'provider_account_reference' => 'sha256:'.hash('sha256', 'campaign-provider-account'),
        'gross_amount_minor' => $amountMinor,
        'fee_amount_minor' => 0,
        'net_amount_minor' => $amountMinor,
        'currency' => 'PHP',
        'provider_status' => $status,
        'occurred_at' => $occurredAt,
        'settled_at' => $status === 'settled'
            ? $occurredAt->addMinute()
            : null,
        'verification_source' => 'campaign-payment-test',
        'payload_hash' => hash('sha256', $transactionId.'-'.$status.'-payload'),
        'metadata' => [
            'destination_verified' => true,
            'settlement_rail' => 'INSTAPAY',
            'normalization_version' => 'test-v1',
        ],
    ]);
}

/**
 * @return array{account_funding_receipts: int, funding_settlements: int, vouchers: int, wallet_transactions: int, treasury_operations: int}
 */
function campaignPaymentFinancialCounts(): array
{
    return [
        'account_funding_receipts' => AccountFundingReceipt::query()->count(),
        'funding_settlements' => FundingSettlement::query()->count(),
        'vouchers' => Voucher::query()->count(),
        'wallet_transactions' => Transaction::query()->count(),
        'treasury_operations' => TreasuryInventoryOperation::query()->count(),
    ];
}
