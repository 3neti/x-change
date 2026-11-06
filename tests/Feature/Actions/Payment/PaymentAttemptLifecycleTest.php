<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Contracts\DirectQrFundingInstructionIssuer;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Payment\ApplyReviewedPayment;
use LBHurtado\XChange\Actions\Payment\ApplyReviewedSplitPayment;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\CreditReviewedExcess;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use LBHurtado\XChange\Actions\Payment\MonitorPaymentAttempt;
use LBHurtado\XChange\Actions\Payment\RecordObservedPaymentTransaction;
use LBHurtado\XChange\Actions\Payment\RecordVoucherCollection;
use LBHurtado\XChange\Actions\Payment\SettleAdditionalQrPayment;
use LBHurtado\XChange\Actions\Payment\SettleVerifiedPaymentAttempt;
use LBHurtado\XChange\Actions\Payment\TransitionPaymentAttempt;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Actions\Redemption\PrepareVoucherClaimEvidence;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Actions\Settlement\VerifyPhilhealthSettlementAmount;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\SettlementReadinessGateContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
use LBHurtado\XChange\Data\Payment\PaymentObservationNoticeData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Events\PaymentTransactionObserved;
use LBHurtado\XChange\Exceptions\LatePaymentRequiresReview;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Jobs\Payment\VerifyAdditionalQrPaymentJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Models\PartnerPaymentEvent;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\V2EvidenceOutbox;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Claim\VoucherClaimFlowCompiler;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\FundingQrMerchantProfileResolver;
use LBHurtado\XChange\Services\Payment\SessionPaymentReview;
use LBHurtado\XChange\Services\Settlement\IssuerInvoiceDrafts;
use LBHurtado\XChange\Services\Settlement\V2EventWebhookDelivery;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XChange\Services\SettlementEnvelopeMetadataSyncService;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LBHurtado\XChange\Support\Funding\FundingMerchantSnapshot;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XFeedback\Contracts\FeedbackWebhookSenderContract;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackWebhookMessageData;
use LBHurtado\XFeedback\Data\FeedbackWebhookSendResultData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

class SpecificPaymentTestAdapter extends FakeFundingProviderAdapter implements SpecificIncomingPaymentVerifier
{
    public array $specificObservations = [];

    public ?Throwable $specificFailure = null;

    public int $specificCalls = 0;

    public function verifyIncomingPayment(FundingVerificationData $verification, string $providerTransactionId): ProviderFundingObservationData
    {
        $this->specificCalls++;
        if ($this->specificFailure !== null) {
            throw $this->specificFailure;
        }

        return $this->specificObservations[$providerTransactionId];
    }
}

beforeEach(function (): void {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.expires_after_minutes', 15);

    $this->paymentAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->paymentAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('allows a persisted local reviewer through the temporary settlement review sentinel', function (): void {
    $reviewer = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($reviewer);

    $unsaved = new ($reviewer::class);
    expect(Gate::forUser($reviewer)->allows('x-change.settlement.verify-amount', $voucher))->toBeTrue()
        ->and(Gate::forUser($unsaved)->allows('x-change.settlement.verify-amount', $voucher))->toBeFalse()
        ->and(Gate::forUser(null)->allows('x-change.settlement.verify-amount', $voucher))->toBeFalse();
    $originalEnvironment = app()->environment();
    try {
        app()->instance('env', 'production');
        expect(Gate::forUser($reviewer)->allows('x-change.settlement.verify-amount', $voucher))->toBeFalse();
        app()->instance('env', 'staging');
        expect(Gate::forUser($reviewer)->allows('x-change.settlement.verify-amount', $voucher))->toBeFalse();
    } finally {
        app()->instance('env', $originalEnvironment);
    }
});

it('follows the PhilHealth v2 attestation and verified settlement lifecycle', function (): void {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 100, 'max_amount' => 5_000_000]);
    Queue::fake();
    enableNetbankTreasuryForTests();
    $user = actingAsTestUser();
    fundTestUserClientFunds($user, 10000);
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(inventoryReference: 'inventory:netbank:vca-cash', resourceType: 'cash_at_bank', currency: 'PHP', capacityMinor: 0, status: 'requested', idempotencyKey: 'register:philhealth', externalReference: 'resource:netbank:corporate-vca'));
    $inventory->recognize(new TreasuryInventoryRecognitionData(operationReference: 'philhealth:test-funding', inventoryReference: 'inventory:netbank:vca-cash', settlementResourceReference: 'resource:netbank:corporate-vca', amountMinor: 10000, currency: 'PHP', status: 'requested', idempotencyKey: 'philhealth:test-funding', externalReference: 'philhealth:test'));
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    config()->set('x-change.commercial.enabled', true);
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:philhealth-lifecycle');
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($user, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $payload = [
        'cash' => ['amount' => 0, 'currency' => 'PHP', 'settlement_rail' => 'INSTAPAY', 'validation' => ['country' => 'PH']],
        'inputs' => ['fields' => ['name']],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null, 'webhooks' => [
            ['url' => 'https://example.test/ready', 'events' => ['settlement.ready']],
        ]],
        'rider' => [],
        'count' => 1, 'prefix' => 'PAY', 'mask' => '****',
        'voucher_type' => 'settlement', 'target_amount' => 100,
        'metadata' => ['collection_wallet_id' => (int) $user->wallet->id],
        'execution' => [
            'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
            'settlement' => ['driver' => 'philhealth-bst', 'version' => '1.0.0', 'readiness_gate' => 'settleable'],
            'document_requirements' => [['type' => 'CLAIM_FORM', 'timing' => 'before_payment', 'requires_acceptance' => false, 'contributor' => 'claimant']],
            'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'],
            'events' => [
                'attest' => ['actor' => 'beneficiary', 'requires' => [], 'surface' => 'claim', 'authentication' => 'anonymous'],
                'review' => ['actor' => 'hospital_reviewer', 'requires' => ['attestation_recorded'], 'surface' => 'claim', 'authentication' => 'required'],
                'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready', 'confirmed_payment'], 'surface' => 'pay', 'authentication' => 'optional'],
            ],
        ],
    ];
    $payload['provider'] = 'netbank';
    $payload['issuer_id'] = $user->getKey();
    $payload['fee_bearer'] = 'issuer';
    data_set($payload, 'metadata.flow_type', 'settlement');
    data_set($payload, 'metadata.settlement_driver', 'philhealth-bst');
    data_set($payload, 'metadata.issuer_id', (string) $user->id);
    $issued = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($issued->voucher_id);
    expect(data_get($voucher->metadata, 'instructions.execution.schema'))->toBe('voucher.execution.v2');
    auth()->logout();
    config()->set('inertia.testing.ensure_pages_exist', false);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $this->withHeader('X-Inertia', 'true');
    $this->get('/x/claim/'.$voucher->code)->assertOk()->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.claim_documents.requirements.0.type', 'CLAIM_FORM');
    $this->postJson(route('x-change.claim.documents.store', ['code' => $voucher->code]), [
        'document_type' => 'CLAIM_FORM', 'file' => UploadedFile::fake()->createWithContent('claim.pdf', "%PDF-1.4\nexample\n%%EOF"),
    ])->assertCreated();
    $flow = app(VoucherClaimFlowCompiler::class)->compile($voucher)->instructions->toArray();
    $fields = collect($flow['steps'])->flatMap(fn (array $step) => data_get($step, 'config.fields', []))->pluck('name')->all();
    expect($fields)->toContain('mobile')->not->toContain('bank_code', 'account_number');
    $this->actingAs($user);
    $claimPayload = [
        'mobile' => '09171234567', 'inputs' => ['name' => 'Patient'],
        '_meta' => ['idempotency_key' => 'philhealth-attestation'],
    ];
    app(SubmitPayCodeClaim::class)->handle($voucher, $claimPayload);
    app(SubmitPayCodeClaim::class)->handle($voucher->refresh(), $claimPayload);
    expect(data_get($voucher->fresh()->metadata, 'settlement_envelope.attestation.claim_id'))->not->toBeNull();
    expect($voucher->claims()->count())->toBe(1);
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', ['driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers']);
    $envelopes = app(EnvelopeService::class);
    $envelope = $voucher->envelope()->firstOrFail();
    expect($envelope->driver_id)->toBe('philhealth.bst.demo');
    $envelopes->updatePayload($envelope, [
        'patient_name' => 'Patient', 'patient_mobile' => '09171234567', 'reference' => 'CLAIM-123',
    ], $user);
    foreach (['HOSPITAL_BILL'] as $type) {
        $envelopes->uploadAttachment($envelope, $type, UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\nexample\n%%EOF"), $user, storageDisk: 'local');
    }
    expect($envelope->fresh()->isSettleable())->toBeFalse();
    auth()->logout();
    $this->get('/x/claim/'.$voucher->code)->assertOk()->assertJsonPath('component', 'x-change/claim/SettlementWaiting')
        ->assertJsonPath('props.reviewed', false)->assertJsonMissingPath('props.evidence');
    $this->getJson(route('x-change.claim.settlement-review.show', ['code' => $voucher->code]))->assertUnauthorized();
    $this->actingAs($user);
    $this->get('/x/claim/'.$voucher->code)->assertRedirect(route('x-change.claim.settlement-review.show', ['code' => $voucher->code]));
    $this->get(route('x-change.claim.settlement-review.show', ['code' => $voucher->code]))->assertOk()
        ->assertJsonPath('component', 'x-change/claim/SettlementReview')->assertJsonPath('props.amountMinor', 10000)
        ->assertJsonPath('props.reviewed', false)->assertJsonStructure(['props' => ['evidence']]);
    expect(fn () => app(CreatePaymentAttempt::class)->handle($voucher->fresh(), 'netbank', 'philhealth-payer', 'early'))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    $metadata = $voucher->fresh()->metadata;
    data_set($metadata, 'settlement_envelope.checklist.amount_verified', true);
    $voucher->forceFill(['metadata' => $metadata])->save();
    expect(fn () => app(CreatePaymentAttempt::class)->handle($voucher->fresh(), 'netbank', 'philhealth-payer', 'forged-review'))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    $sentinel = Gate::abilities()['x-change.settlement.verify-amount'];
    Gate::define('x-change.settlement.verify-amount', fn (): bool => false);
    $this->get(route('x-change.claim.settlement-review.show', ['code' => $voucher->code]))->assertForbidden();
    $this->post(route('x-change.claim.settlement-review.store', ['code' => $voucher->code]), ['amount_minor' => 10000, 'evidence_manifest' => str_repeat('a', 64)])->assertForbidden();
    expect(fn () => app(VerifyPhilhealthSettlementAmount::class)->handle($voucher->fresh(), $user, 10000, 'hospital-review-1'))
        ->toThrow(AuthorizationException::class);
    Gate::define('x-change.settlement.verify-amount', $sentinel);
    expect(fn () => app(VerifyPhilhealthSettlementAmount::class)->handle($voucher->fresh(), $user, 9900, 'hospital-review-1'))
        ->toThrow(LogicException::class, 'must match');
    expect(fn () => DB::transaction(function () use ($voucher, $user): void {
        app(VerifyPhilhealthSettlementAmount::class)->handle($voucher->fresh(), $user, 10000, 'rolled-back-review');
        throw new RuntimeException('rollback review');
    }))->toThrow(RuntimeException::class, 'rollback review');
    expect(data_get($voucher->fresh()->metadata, 'settlement_envelope.amount_review'))->toBeNull()
        ->and($envelope->fresh()->getSignalBool('amount_verified'))->toBeFalse()
        ->and(V2EvidenceOutbox::query()->where('event_type', 'settlement.amount_reviewed')->count())->toBe(0)
        ->and(V2EvidenceOutbox::query()->where('event_type', 'settlement.ready')->count())->toBe(0);
    $incomplete = $voucher->fresh()->metadata;
    data_forget($incomplete, 'settlement_envelope.payload.patient_mobile');
    data_forget($incomplete, 'settlement_payload.patient_mobile');
    $voucher->forceFill(['metadata' => $incomplete])->save();
    $reference = 'claim-review:'.data_get($voucher->fresh()->metadata, 'settlement_envelope.attestation.claim_id');
    $this->postJson(route('x-change.claim.settlement-review.store', ['code' => $voucher->code]), ['amount_minor' => 10000, 'evidence_manifest' => str_repeat('a', 64)])->assertUnprocessable();
    $this->post(route('x-change.claim.settlement-review.store', ['code' => $voucher->code]), [
        'amount_minor' => 10000, 'evidence_manifest' => data_get($voucher->fresh()->metadata, 'settlement_envelope.attestation.evidence_manifest_sha256'),
    ])
        ->assertRedirect(route('x-change.claim.settlement-review.show', ['code' => $voucher->code]));
    $voucher->refresh();
    expect($envelope->fresh()->getSignalBool('amount_verified'))->toBeTrue()
        ->and($envelope->fresh()->isSettleable())->toBeTrue();
    app(VerifyPhilhealthSettlementAmount::class)->handle($voucher, $user, 10000, $reference);
    expect(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.amount_reviewed')->count())->toBe(1);
    expect(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.ready')->count())->toBe(0);
    app(SettlementEnvelopeMetadataSyncService::class)->syncEvidence($voucher, ['payload' => ['patient_mobile' => '09171234567']]);
    expect(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.ready')->count())->toBe(1);
    $sender = new class implements FeedbackWebhookSenderContract
    {
        public array $events = [];

        public function send(FeedbackWebhookMessageData $message): FeedbackWebhookSendResultData
        {
            $this->events[] = $message->payload['event_type'];

            return new FeedbackWebhookSendResultData(message_id: 'ready-webhook', status: FeedbackDeliveryData::StatusQueued);
        }
    };
    app()->instance(FeedbackWebhookSenderContract::class, $sender);
    app(V2EvidenceEventJournal::class)->deliverPending();
    app(V2EvidenceEventJournal::class)->deliverPending();
    expect($sender->events)->toBe(['settlement.ready']);
    $attempt = issuedPaymentAttempt($voucher->refresh());
    expect($attempt->expected_amount_minor)->toBe(10000);
    expect(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.ready')->count())->toBe(1);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
    $settled = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    $replayed = app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);
    $payout = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->where('claim_type', 'value_flow')->sole();
    $provider = fakePayoutProvider()->willReturnFailedResult(provider: 'netbank', metadata: ['provider_submission_accepted' => false]);
    $service = app(ValueFlowPayoutService::class);
    $service->submit($payout->id);
    $service->converge($payout);
    expect(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.payout_failed')->count())->toBe(1);
    expect($settled->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($replayed->voucher_collection_id)->toBe($settled->voucher_collection_id)
        ->and($provider->disburseCallCount)->toBe(1)
        ->and($provider->lastRequest->account_number)->toBe('09179999999')
        ->and($payout->fresh()->internal_status)->toBe('recovery_opened');
    expect(fn () => $service->retryUnacceptedSubmission($payout->id, false, 'provider-rejection'))
        ->toThrow(RuntimeException::class, 'explicit no-transfer');
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(provider: 'netbank');
    $service->retryUnacceptedSubmission($payout->id, true, 'provider-rejection');
    expect($payout->fresh()->internal_status)->toBe('finalized')->and($provider->disburseCallCount)->toBe(1);
    expect(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.payout_confirmed')->count())->toBe(1);
});

it('issues, collects, and recovers the same payable lifecycle under both instruction versions', function (string $schema, array $flow): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 100, 'max_amount' => 5_000_000]);
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    config()->set('x-change.commercial.enabled', true);
    $issuer = actingAsTestUser(0);
    fundTestUserClientFunds($issuer, 10000);
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(inventoryReference: 'inventory:netbank:vca-cash', resourceType: 'cash_at_bank', currency: 'PHP', capacityMinor: 0, status: 'requested', idempotencyKey: 'register:parity', externalReference: 'resource:netbank:corporate-vca'));
    $inventory->recognize(new TreasuryInventoryRecognitionData(operationReference: 'parity:test-funding', inventoryReference: 'inventory:netbank:vca-cash', settlementResourceReference: 'resource:netbank:corporate-vca', amountMinor: 10000, currency: 'PHP', status: 'requested', idempotencyKey: 'parity:test-funding', externalReference: 'parity:test'));
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:parity');
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $payload = validPayCodePayload(0, 'INSTAPAY', [
        'provider' => 'netbank', 'issuer_id' => $issuer->getKey(), 'fee_bearer' => 'issuer',
        'voucher_type' => 'payable', 'target_amount' => 100, 'execution' => ['schema' => $schema, ...$flow],
    ]);
    $payload['inputs']['fields'] = [];
    $issued = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($issued->voucher_id);
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
    $settled = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    $replay = app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);
    $payout = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->where('claim_type', 'value_flow')->sole();
    $failed = fakePayoutProvider()->willReturnFailedResult(provider: 'netbank', metadata: ['provider_submission_accepted' => false]);
    $service = app(ValueFlowPayoutService::class);
    $service->submit($payout->id);
    expect($settled->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($replay->voucher_collection_id)->toBe($settled->voucher_collection_id)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and($failed->lastRequest->account_number)->toBe('09179999999')
        ->and($payout->fresh()->internal_status)->toBe('recovery_opened');
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(provider: 'netbank');
    $service->retryUnacceptedSubmission($payout->id, true, 'provider-rejection');
    expect($payout->fresh()->internal_status)->toBe('finalized')->and($provider->disburseCallCount)->toBe(1);
})->with([
    'v1' => ['voucher.execution.v1', ['metadata' => ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]]],
    'v2' => ['voucher.execution.v2', ['value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']]],
]);

it('journals v2 captured attestation evidence and verified collection once across replay', function (): void {
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0.00, overrides: [
        'inputs' => ['fields' => ['name']],
        'target_amount' => 100.00,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $user->id, 'collection_wallet_id' => $user->wallet->id],
    ]));
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2',
        'events' => ['attest' => ['actor' => 'beneficiary'], 'settle' => ['actor' => 'payer']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();

    $payload = ['inputs' => ['name' => 'Private Patient', 'email' => 'patient@example.test'], '_meta' => ['idempotency_key' => 'attestation-test']];
    $claim = app(PrepareVoucherClaimEvidence::class)->handle($voucher->refresh(), $payload);
    $replayedClaim = app(PrepareVoucherClaimEvidence::class)->handle($voucher->refresh(), [
        '_meta' => ['idempotency_key' => 'attestation-test', 'request_id' => 'retry'],
        'inputs' => ['email' => 'patient@example.test', 'name' => 'Private Patient'],
    ]);
    expect(fn () => app(PrepareVoucherClaimEvidence::class)->handle($voucher->refresh(), [
        'inputs' => ['name' => 'Different Patient'], '_meta' => ['idempotency_key' => 'attestation-test'],
    ]))->toThrow(LogicException::class, 'different evidence');
    $attempt = issuedPaymentAttempt($voucher->refresh());
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
    $settled = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);
    app(V2EvidenceEventJournal::class)->deliverPending();

    $events = ExecutionJournalEntry::query()->whereIn('event_type', ['attestation.evidence_captured', 'settlement.payment_collected'])->orderBy('id')->get();
    expect($replayedClaim->is($claim))->toBeTrue()
        ->and($events->pluck('event_type')->all())->toBe(['attestation.evidence_captured', 'settlement.payment_collected'])
        ->and(json_encode($events->toArray(), JSON_THROW_ON_ERROR))->not->toContain('Private Patient')
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('rolls back the v2 attestation journal with its claim evidence', function (): void {
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => ['fields' => ['name']],
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $user->id],
    ]));
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2', 'events' => ['attest' => ['actor' => 'beneficiary']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();

    expect(fn () => DB::transaction(function () use ($voucher): void {
        app(PrepareVoucherClaimEvidence::class)->handle($voucher->refresh(), [
            'inputs' => ['name' => 'Private Patient'], '_meta' => ['idempotency_key' => 'rollback-test'],
        ]);
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect($voucher->claims()->count())->toBe(0)
        ->and(V2EvidenceOutbox::query()->count())->toBe(0)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'attestation.evidence_captured')->count())->toBe(0);
});

it('rolls back the v2 payment journal with its collection', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2',
        'events' => ['settle' => ['actor' => 'payer']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $attempt = issuedPaymentAttempt($voucher->refresh());
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);

    expect(fn () => DB::transaction(function () use ($attempt): void {
        app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(0)
        ->and(V2EvidenceOutbox::query()->count())->toBe(0)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'settlement.payment_collected')->count())->toBe(0)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::AwaitingPayment);
});

it('rejects a v2 attestation without configured claim evidence', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2', 'events' => ['attest' => ['actor' => 'beneficiary']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();

    expect(fn () => app(PrepareVoucherClaimEvidence::class)->handle($voucher->refresh(), [
        '_meta' => ['idempotency_key' => 'missing-evidence'],
    ]))->toThrow(LogicException::class, 'requires configured claim evidence');
    expect($voucher->claims()->count())->toBe(0);
});

it('recovers a missing v2 payment event from an already settled collection', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
    $settled = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    $metadata = $voucher->fresh()->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2', 'events' => ['settle' => ['actor' => 'payer']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();

    app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);
    app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);
    $failing = Mockery::mock(ExecutionJournalRecorder::class);
    $failing->shouldReceive('record')->once()->andThrow(new RuntimeException('delivery failed'));
    (new V2EvidenceEventJournal($failing, app(V2EventWebhookDelivery::class), app(SettlementCollectionGate::class)))->deliverPending();
    expect(V2EvidenceOutbox::query()->where('status', 'pending')->sole()->attempts)->toBe(1);
    app(V2EvidenceEventJournal::class)->deliverPending();

    expect(ExecutionJournalEntry::query()->where('event_type', 'settlement.payment_collected')->count())->toBe(1)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('records a payable v2 verified-payment subscription only once on replay', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2', 'driver' => 'default',
        'events' => ['settle' => ['actor' => 'payer', 'requires' => ['confirmed_payment'], 'surface' => 'pay', 'authentication' => 'optional']],
    ]);
    data_set($metadata, 'instructions.feedback.webhooks', [
        ['url' => 'https://example.test/events', 'events' => ['settlement.payment_collected']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();

    $attempt = issuedPaymentAttempt($voucher->refresh());
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
    $settled = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);
    app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);

    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.payment_collected')->count())->toBe(1);
});

it('issues, collects and delivers a payable v2 payment event without replaying it', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:v2-payable-lifecycle');
    $issuer = actingAsTestUser(0);
    $drafts = app(IssuerInvoiceDrafts::class);
    $reference = $drafts->create($issuer, UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\nexample\n%%EOF"));
    $drafts->bind($issuer, $reference, 'payment-lifecycle');
    $documentId = $drafts->documentId($issuer, $reference);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    Queue::fake();
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $payload = validPayCodePayload(0, 'INSTAPAY', [
        'provider' => 'netbank', 'issuer_id' => $issuer->getKey(), 'voucher_type' => 'payable',
        'target_amount' => 100, 'fee_bearer' => 'payer',
        'payment_details' => ['description' => 'Repair', 'documents' => [['id' => $documentId, 'type' => 'invoice', 'label' => 'Invoice']]],
        'issuer_invoice_drafts' => [$reference], '_meta' => ['idempotency_key' => 'payment-lifecycle'],
        'feedback' => ['webhooks' => [['url' => 'https://example.test/events', 'events' => ['settlement.payment_collected']]]],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default',
            'events' => ['settle' => ['actor' => 'payer', 'requires' => ['confirmed_payment'], 'surface' => 'pay', 'authentication' => 'optional']]],
    ]);
    $issued = app(GeneratePayCode::class)->handle($payload);
    $voucher = Voucher::query()->findOrFail($issued->voucher_id);
    expect($voucher->envelope()->firstOrFail()->attachments()->sole()->document_id)->toBe($documentId);
    $sender = new class implements FeedbackWebhookSenderContract
    {
        public int $calls = 0;

        public function send(FeedbackWebhookMessageData $message): FeedbackWebhookSendResultData
        {
            $this->calls++;

            return new FeedbackWebhookSendResultData(message_id: 'payable-event', status: FeedbackDeliveryData::StatusQueued);
        }
    };
    app()->instance(FeedbackWebhookSenderContract::class, $sender);
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
    $settled = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    app(SettleVerifiedPaymentAttempt::class)->handle($settled, PaymentVerificationTrigger::Payer);
    app(V2EvidenceEventJournal::class)->deliverPending();
    app(V2EvidenceEventJournal::class)->deliverPending();

    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(V2EvidenceOutbox::query()->where('voucher_id', $voucher->id)->where('event_type', 'settlement.payment_collected')->count())->toBe(1)
        ->and($sender->calls)->toBe(1);
});

it('removes staged attachment copies if commercial posting rolls back issuance', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:v2-payable-rollback');
    $issuer = actingAsTestUser(0);
    $drafts = app(IssuerInvoiceDrafts::class);
    $reference = $drafts->create($issuer, UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\nexample\n%%EOF"));
    $drafts->bind($issuer, $reference, 'payment-rollback');
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $sales = Mockery::mock(PayCodeCommercialSaleService::class);
    $sales->shouldReceive('post')->once()->andThrow(new RuntimeException('simulated posting failure'));
    app()->instance(PayCodeCommercialSaleService::class, $sales);
    $payload = validPayCodePayload(0, 'INSTAPAY', [
        'provider' => 'netbank', 'issuer_id' => $issuer->getKey(), 'voucher_type' => 'payable',
        'target_amount' => 100, 'fee_bearer' => 'payer',
        'payment_details' => ['documents' => [['id' => $drafts->documentId($issuer, $reference), 'type' => 'invoice', 'label' => 'Invoice']]],
        'issuer_invoice_drafts' => [$reference], '_meta' => ['idempotency_key' => 'payment-rollback'],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default'],
    ]);
    expect(fn () => app(GeneratePayCode::class)->handle($payload))->toThrow(RuntimeException::class, 'simulated posting failure');
    expect(Storage::disk('local')->allFiles('envelopes'))->toBe([])
        ->and(DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)->value('voucher_id'))->toBeNull();
});

it('records settlement readiness only after the readiness evaluator permits it', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2',
        'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'],
        'events' => ['review' => ['actor' => 'hospital_reviewer']],
    ]);
    data_set($metadata, 'settlement_envelope.amount_review', ['reference' => 'review-1', 'reviewed_at' => now()->toISOString()]);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $readiness = Mockery::mock(SettlementCollectionGate::class);
    $readiness->shouldReceive('contextFromVoucher')->times(3)->andReturn(['gate' => 'settleable']);
    $readiness->shouldReceive('ensureCollectibleSettlementIsReady')->once()->andThrow(new VoucherRequiresSettlementEnvelope);
    $readiness->shouldReceive('ensureCollectibleSettlementIsReady')->twice()->andReturnNull();
    app()->instance(SettlementCollectionGate::class, $readiness);
    $journal = app(V2EvidenceEventJournal::class);
    $journal->settlementReadyAfterReview($voucher);
    expect(V2EvidenceOutbox::query()->where('event_type', 'settlement.ready')->count())->toBe(0);
    $journal->settlementReadyAfterReview($voucher);
    $journal->settlementReadyAfterReview($voucher);
    expect(V2EvidenceOutbox::query()->where('event_type', 'settlement.ready')->count())->toBe(1);
});

it('retries subscribed v2 webhooks without repeating successful delivery', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.feedback.webhooks', [
        ['url' => 'https://example.test/events', 'events' => ['settlement.payment_collected']],
        ['url' => 'https://example.test/other', 'events' => ['attestation.evidence_captured']],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $event = V2EvidenceOutbox::query()->create([
        'event_key' => 'test:payment:1', 'voucher_id' => $voucher->id,
        'event_type' => 'settlement.payment_collected', 'evidence_reference' => 'collection:1',
        'occurred_at' => now(), 'payload' => ['amount_minor' => 10000, 'currency' => 'PHP'],
    ]);
    $sender = new class implements FeedbackWebhookSenderContract
    {
        public int $calls = 0;

        public ?FeedbackWebhookMessageData $message = null;

        public function send(FeedbackWebhookMessageData $message): FeedbackWebhookSendResultData
        {
            $this->calls++;
            $this->message = $message;
            if ($this->calls === 1) {
                throw new RuntimeException('temporary failure');
            }

            return new FeedbackWebhookSendResultData(message_id: 'test-webhook', status: FeedbackDeliveryData::StatusQueued);
        }
    };
    app()->instance(FeedbackWebhookSenderContract::class, $sender);

    app(V2EvidenceEventJournal::class)->deliverPending();
    expect($event->fresh()->status)->toBe('pending')->and($sender->calls)->toBe(1);
    app(V2EvidenceEventJournal::class)->deliverPending();
    app(V2EvidenceEventJournal::class)->deliverPending();
    expect($event->fresh()->status)->toBe('delivered')->and($sender->calls)->toBe(2)
        ->and($sender->message->payload['causation_id'])->toBe('test:payment:1')
        ->and($sender->message->payload['event_type'])->toBe('settlement.payment_collected')
        ->and(ExecutionJournalEntry::query()->where('event_type', 'settlement.payment_collected')->count())->toBe(1);
});

it('records multiple provider payments per QR without duplicating or exposing payer identity', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'monitor-session', 'monitor-request');
    $record = app(RecordObservedPaymentTransaction::class);

    $firstData = new ObservedPaymentTransactionData(
        providerTransactionId: 'netbank-credit-1',
        amountMinor: 2500,
        currency: 'PHP',
        providerStatus: 'settled',
        payerName: 'Apple Hurtado',
        payerAccountNumber: '09175180722',
        payerInstitutionCode: 'GCASH',
    );
    $first = $record->handle($attempt, $firstData);
    $replay = $record->handle($attempt, $firstData);
    $second = $record->handle($attempt, new ObservedPaymentTransactionData(
        providerTransactionId: 'netbank-credit-2',
        amountMinor: 5000,
        currency: 'PHP',
        providerStatus: 'settled',
        payerMobile: '09175180722',
    ));
    $raw = DB::table('x_change_observed_payment_transactions')->find($first->getKey());

    expect($replay->is($first))->toBeTrue()
        ->and($attempt->observedPayments()->count())->toBe(2)
        ->and($second->amount_minor)->toBe(5000)
        ->and($first->payer_mobile_ciphertext)->toBeNull()
        ->and($raw->payer_name_ciphertext)->not->toContain('Apple Hurtado')
        ->and($raw->payer_account_ciphertext)->not->toContain('09175180722')
        ->and($raw->provider_transaction_id_ciphertext)->not->toContain('netbank-credit-1')
        ->and(ExecutionJournalEntry::query()->where('event_type', 'payment.provider_observed')->count())->toBe(2)
        ->and(json_encode(ExecutionJournalEntry::query()->where('event_type', 'payment.provider_observed')->firstOrFail()->toArray()))
        ->not->toContain('Apple Hurtado')
        ->not->toContain('09175180722')
        ->and(array_key_exists('payer_account_ciphertext', $first->toArray()))->toBeFalse();
});

it('refuses to reattribute an observed provider transaction to another QR', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $first = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'monitor-first', 'monitor-first');
    $second = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'monitor-second', 'monitor-second');
    $payment = new ObservedPaymentTransactionData('same-provider-credit', 2500, 'PHP', 'settled');
    app(RecordObservedPaymentTransaction::class)->handle($first, $payment);

    expect(fn () => app(RecordObservedPaymentTransaction::class)->handle($second, $payment))
        ->toThrow(LogicException::class)
        ->and(ObservedPaymentTransaction::query()->count())->toBe(1);
});

it('records provider status changes once without altering collection state', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'status-session', 'status-request');
    $record = app(RecordObservedPaymentTransaction::class);
    $pending = $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'pending'));
    $settled = $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'settled'));
    $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'settled'));
    $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2500, 'PHP', 'pending'));

    expect($settled->is($pending))->toBeTrue()
        ->and($pending->statuses()->pluck('provider_status')->all())->toBe(['pending', 'settled', 'pending'])
        ->and(ExecutionJournalEntry::query()->where('event_type', 'payment.provider_observed')->count())->toBe(3)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and(DB::table('voucher_collections')->count())->toBe(0);

    expect(fn () => $record->handle($attempt, new ObservedPaymentTransactionData('changing-credit', 2600, 'PHP', 'settled')))
        ->toThrow(LogicException::class);
});

it('records changed provider transaction evidence for review instead of repeatedly failing monitoring', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->incomingPayments = [new ObservedPaymentTransactionData('changing-amount', 2500, 'PHP', 'settled')];
    app(MonitorPaymentAttempt::class)->handle($attempt);
    $this->paymentAdapter->incomingPayments = [new ObservedPaymentTransactionData('changing-amount', 2600, 'PHP', 'settled')];
    app(MonitorPaymentAttempt::class)->handle($attempt->fresh());
    app(MonitorPaymentAttempt::class)->handle($attempt->fresh());
    expect(DB::table('x_change_payment_transaction_reviews')->count())->toBe(1)
        ->and(DB::table('x_change_payment_transaction_reviews')->value('reason_code'))->toBe('payment_evidence_changed')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('opens one review and stops repeated verification when provider evidence is ambiguous', function (): void {
    Queue::fake();
    $attempt = issuedPaymentAttempt(paymentAttemptCollectibleVoucher());
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->incomingPayments = [new ObservedPaymentTransactionData('ambiguous-transaction', $attempt->expected_amount_minor, 'PHP', 'settled')];
    $adapter->specificFailure = new ProviderFundingVerificationIndeterminate('ambiguous evidence');
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorPaymentAttempt::class)->handle($attempt);
    $transaction = $attempt->observedPayments()->sole();
    app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    expect($adapter->specificCalls)->toBe(1)
        ->and(DB::table('x_change_payment_transaction_reviews')->value('reason_code'))->toBe('provider_evidence_ambiguous')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('credits a verified excess payment to the issuer once with an audited operator decision', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 20,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $issuer->id, 'collection_wallet_id' => $issuer->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher);
    recordPaymentAttemptCollection($voucher, 20);
    $data = exactPaymentObservation($attempt);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'excess_payment',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $principal = app(TreasuryPrincipalReferenceResolverContract::class)->resolve($issuer);
    $clientFunds = fn (): int => collect(app(TreasuryPositionReadModelContract::class)->forPrincipal($principal))
        ->first(fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds)?->balanceMinor ?? 0;
    $balance = $clientFunds();
    $action = app(CreditReviewedExcess::class);
    $incorrectEvidence = clone $data;
    $incorrectEvidence->fundingAddress = 'sha256:'.hash('sha256', 'different-destination');
    $incorrectEvidence->payloadHash = hash('sha256', 'incorrect-excess-evidence');
    $adapter->specificObservations[$data->providerTransactionId] = $incorrectEvidence;
    expect(fn () => $action->handle($reviewId, $issuer, 'Verified extra payment'))
        ->toThrow(RuntimeException::class, 'not eligible');
    expect($clientFunds())->toBe($balance)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('status'))->toBe('open');
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    Gate::define('x-change.recovery.manage', fn ($actor): bool => $actor->is($issuer));
    $this->actingAs($issuer)->post("/x/operations/recovery/payments/{$reviewId}/credit-excess", [
        'reason' => 'Verified extra payment', 'confirmed' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $intent = FundingIntent::query()->findOrFail(
        DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('resolution_funding_intent_id'),
    );
    $replay = $action->handle($reviewId, $issuer, 'Verified extra payment');
    $review = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->first();
    expect($intent->is($replay))->toBeTrue()
        ->and($adapter->specificCalls)->toBe(2)
        ->and($clientFunds())->toBe($balance + 2000)
        ->and($review->resolution_code)->toBe('credited_client_funds')
        ->and(Crypt::decryptString($review->resolution_note_ciphertext))->toBe('Verified extra payment')
        ->and(DB::table('x_change_funding_settlements')->where('funding_intent_id', $intent->id)->count())->toBe(1);
});

it('applies a reviewed payment only to a matching candidate and records the decision once', function (): void {
    Queue::fake();
    $issuer = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($issuer);
    $first = issuedPaymentAttempt($voucher);
    $second = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'review-second-session', 'review-second-request',
    ));
    $data = exactPaymentObservation($second);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($first, new ObservedPaymentTransactionData(
        $data->providerTransactionId, $second->expected_amount_minor, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'ambiguous',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('x_change_payment_review_attempts')->insert(['review_id' => $reviewId, 'payment_attempt_id' => $second->id]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $action = app(ApplyReviewedPayment::class);
    expect(fn () => $action->handle($reviewId, $first->id, $issuer, 'Checked provider evidence'))
        ->toThrow(RuntimeException::class, 'not a review candidate');
    $wrongDestination = clone $data;
    $wrongDestination->fundingAddress = 'sha256:'.hash('sha256', 'another-destination');
    $wrongDestination->payloadHash = hash('sha256', 'different-provider-evidence');
    $adapter->specificObservations[$data->providerTransactionId] = $wrongDestination;
    expect(fn () => $action->handle($reviewId, $second->id, $issuer, 'Checked provider evidence'))
        ->toThrow(RuntimeException::class, 'does not match');
    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(0)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('status'))->toBe('open');
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    Gate::define('x-change.recovery.manage', fn ($actor): bool => $actor->is($issuer));
    $this->actingAs($issuer)->post("/x/operations/recovery/payments/{$reviewId}/apply", [
        'reason' => 'Checked provider evidence', 'confirmed' => true, 'attempt_id' => $second->id,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $collection = VoucherCollection::query()->findOrFail(
        DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('resolution_voucher_collection_id'),
    );
    $replay = $action->handle($reviewId, $second->id, $issuer, 'Checked provider evidence');
    $review = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->first();
    expect($collection->is($replay))->toBeTrue()
        ->and($review->resolution_payment_attempt_id)->toBe($second->id)
        ->and(Crypt::decryptString($review->resolution_note_ciphertext))->toBe('Checked provider evidence')
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and($adapter->specificCalls)->toBe(2);
    expect(fn () => $action->handle($reviewId, $first->id, $issuer, 'Checked provider evidence'))
        ->toThrow(RuntimeException::class, 'different attempt');
});

it('serializes concurrent operator credits for the same excess evidence', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $issuer = actingAsTestUser();
    fundTestUserClientFunds($issuer, 10000);
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 20,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $issuer->id, 'collection_wallet_id' => $issuer->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher);
    recordPaymentAttemptCollection($voucher, 20);
    $data = exactPaymentObservation($attempt);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'excess_payment',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $children = [];
    $channels = [];
    $paths = [];
    DB::purge();
    for ($i = 0; $i < 2; $i++) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $path = tempnam(sys_get_temp_dir(), 'excess-race-');
        if ($channel === false || $path === false) {
            throw new RuntimeException('Unable to prepare concurrent review.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to start concurrent review.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            foreach (array_keys(app()->getBindings()) as $binding) {
                if (str_starts_with($binding, 'Bavix\\Wallet\\')) {
                    app()->forgetInstance($binding);
                }
            }
            fread($channel[1], 1);
            try {
                $result = app(CreditReviewedExcess::class)->handle($reviewId, $issuer, 'Verified extra payment');
                file_put_contents($path, (string) $result->id);
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($path, get_class($exception).': '.$exception->getMessage());
                exit(1);
            }
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $paths[] = $path;
    }
    foreach ($channels as $channel) {
        fwrite($channel, 'x');
        fclose($channel);
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }
    DB::purge();
    $results = array_map(fn (string $path): string => trim((string) file_get_contents($path)), $paths);
    foreach ($paths as $path) {
        unlink($path);
    }
    expect($results[0])->toMatch('/^\d+$/')->and($results[1])->toBe($results[0])
        ->and(DB::table('x_change_funding_settlements')->where('funding_intent_id', (int) $results[0])->count())->toBe(1)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('resolution_code'))->toBe('credited_client_funds');
});

it('serializes concurrent operator decisions for an ambiguous payment', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    Queue::fake();
    $issuer = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($issuer);
    $first = issuedPaymentAttempt($voucher);
    $second = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'race-second-session', 'race-second-request',
    ));
    $data = exactPaymentObservation($second);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($first, new ObservedPaymentTransactionData(
        $data->providerTransactionId, $second->expected_amount_minor, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'ambiguous',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([$first->id, $second->id] as $attemptId) {
        DB::table('x_change_payment_review_attempts')->insert(['review_id' => $reviewId, 'payment_attempt_id' => $attemptId]);
    }
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $children = [];
    $channels = [];
    $paths = [];
    DB::purge();
    for ($i = 0; $i < 2; $i++) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $path = tempnam(sys_get_temp_dir(), 'ambiguous-race-');
        if ($channel === false || $path === false) {
            throw new RuntimeException('Unable to prepare concurrent review.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to start concurrent review.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            foreach (array_keys(app()->getBindings()) as $binding) {
                if (str_starts_with($binding, 'Bavix\\Wallet\\')) {
                    app()->forgetInstance($binding);
                }
            }
            fread($channel[1], 1);
            try {
                $result = app(ApplyReviewedPayment::class)->handle($reviewId, $second->id, $issuer, 'Checked provider evidence');
                file_put_contents($path, (string) $result->id);
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($path, get_class($exception).': '.$exception->getMessage());
                exit(1);
            }
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $paths[] = $path;
    }
    foreach ($channels as $channel) {
        fwrite($channel, 'x');
        fclose($channel);
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }
    DB::purge();
    $results = array_map(fn (string $path): string => trim((string) file_get_contents($path)), $paths);
    foreach ($paths as $path) {
        unlink($path);
    }
    expect($results[0])->toMatch('/^\d+$/')->and($results[1])->toBe($results[0])
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('resolution_payment_attempt_id'))->toBe($second->id);
});

it('atomically collects the target and credits an excess from one reviewed provider payment', function (bool $v2): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $issuer = actingAsTestUser();
    fundTestUserClientFunds($issuer, 10000);
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'voucher_type' => 'payable', 'target_amount' => 100,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $issuer->id, 'collection_wallet_id' => $issuer->wallet->id],
    ]));
    if ($v2) {
        $metadata = $voucher->metadata;
        data_set($metadata, 'instructions.execution', ['schema' => 'voucher.execution.v2', 'events' => ['settle' => ['actor' => 'payer']]]);
        $voucher->forceFill(['metadata' => $metadata])->save();
        $voucher->refresh();
    }
    $attempt = issuedPaymentAttempt($voucher);
    $data = exactPaymentObservation($attempt, amountMinor: 12000);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, 12000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'payment_attribution_or_eligibility',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('x_change_payment_review_attempts')->insert(['review_id' => $reviewId, 'payment_attempt_id' => $attempt->id]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $principal = app(TreasuryPrincipalReferenceResolverContract::class)->resolve($issuer);
    $balance = fn (): int => collect(app(TreasuryPositionReadModelContract::class)->forPrincipal($principal))
        ->first(fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds)?->balanceMinor ?? 0;
    $before = $balance();
    $action = app(ApplyReviewedSplitPayment::class);
    expect(fn () => $action->handle($reviewId, $attempt->id, $issuer, 'Verified the full payment', 10000, 3000))
        ->toThrow(RuntimeException::class, 'does not require')
        ->and(DB::table('x_change_reviewed_payment_splits')->count())->toBe(0)
        ->and($balance())->toBe($before);
    expect(fn () => DB::transaction(function () use ($action, $reviewId, $attempt, $issuer): void {
        $action->handle($reviewId, $attempt->id, $issuer, 'Verified the full payment', 10000, 2000);
        throw new RuntimeException('Simulated transaction failure');
    }))->toThrow(RuntimeException::class, 'Simulated transaction failure');
    expect(DB::table('x_change_reviewed_payment_splits')->count())->toBe(0)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(0)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('status'))->toBe('open')
        ->and($balance())->toBe($before);
    Gate::define('x-change.recovery.manage', fn ($actor): bool => $actor->is($issuer));
    $this->actingAs($issuer)->post("/x/operations/recovery/payments/{$reviewId}/split", [
        'reason' => 'Verified the full payment', 'confirmed' => true, 'attempt_id' => $attempt->id,
        'expected_collection_minor' => 10000, 'expected_excess_minor' => 2000,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $collection = VoucherCollection::query()->findOrFail(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('resolution_voucher_collection_id'));
    $replay = $action->handle($reviewId, $attempt->id, $issuer, 'Verified the full payment', 10000, 2000);
    app(SettleVerifiedPaymentAttempt::class)->handle($attempt->fresh(), PaymentVerificationTrigger::Payer);
    expect($collection->is($replay))->toBeTrue()
        ->and($collection->collected_amount_minor)->toBe(10000)
        ->and($balance())->toBe($before + 12000)
        ->and(data_get($collection->meta, 'posting.provider_gross_amount_minor'))->toBe(12000)
        ->and(data_get($collection->meta, 'posting.excess_amount_minor'))->toBe(2000)
        ->and((int) DB::table('treasury_inventories')->where('inventory_reference', 'inventory:netbank:vca-cash')->value('balance_minor'))->toBe(12000)
        ->and(DB::table('x_change_reviewed_payment_splits')->where('review_id', $reviewId)->value('excess_amount_minor'))->toBe(2000)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->value('resolution_code'))->toBe('split_payment')
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
})->with([false, true]);

it('includes the payer fee once when splitting a reviewed payment', function (): void {
    Queue::fake();
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:split-fee');
    $issuer = actingAsTestUser(0);
    enableNetbankTreasuryForTests();
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    app(TreasuryAccountPortfolioProvisioningContract::class)->provision($issuer, ['netbank-primary']);
    $funding = Mockery::mock(ProviderFundingPolicyContract::class);
    $funding->shouldReceive('assertCanIssue')->once()->andReturn(FundingDecisionData::allowed(authority: 'local_ledger', availableMinor: 10000, requiredMinor: 0, currency: 'PHP', meta: ['provider' => 'netbank', 'topology' => 'ledger_pooled']));
    app()->instance(ProviderFundingPolicyContract::class, $funding);
    $issued = app(GeneratePayCode::class)->handle(validPayCodePayload(0, 'INSTAPAY', [
        'provider' => 'netbank', 'issuer_id' => $issuer->getKey(), 'voucher_type' => 'payable', 'target_amount' => 100,
        'fee_bearer' => 'payer', '_meta' => ['idempotency_key' => 'split-fee-lifecycle'],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default'],
    ]));
    $voucher = Voucher::query()->findOrFail($issued->voucher_id);
    $fee = app(PayCodeCommercialSaleService::class)->outstandingCollectionFee($voucher);
    expect($fee)->toBeGreaterThan(0);
    $attempt = issuedPaymentAttempt($voucher);
    $gross = 10000 + $fee + 2000;
    $data = exactPaymentObservation($attempt, amountMinor: $gross);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, $gross, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'payment_attribution_or_eligibility',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('x_change_payment_review_attempts')->insert(['review_id' => $reviewId, 'payment_attempt_id' => $attempt->id]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $collection = app(ApplyReviewedSplitPayment::class)->handle($reviewId, $attempt->id, $issuer, 'Verified payment and fee', 10000 + $fee, 2000);
    expect($collection->collected_amount_minor)->toBe(10000)
        ->and(data_get($collection->meta, 'fee_amount_minor'))->toBe($fee)
        ->and(data_get($collection->meta, 'posting.excess_amount_minor'))->toBe(2000)
        ->and(data_get($collection->meta, 'posting.provider_gross_amount_minor'))->toBe($gross)
        ->and(app(PayCodeCommercialSaleService::class)->outstandingCollectionFee($voucher))->toBe(0);
});

it('splits a later payment on an already settled QR without changing its first collection', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $issuer = actingAsTestUser();
    fundTestUserClientFunds($issuer, 10000);
    $voucher = paymentAttemptCollectibleVoucherForUser($issuer);
    $attempt = issuedPaymentAttempt($voucher);
    PaymentAttempt::query()->whereKey($attempt->id)->update(['expected_amount_minor' => 7000]);
    $attempt = $attempt->fresh();
    $firstEvidence = exactPaymentObservation($attempt);
    $firstEvidence->occurredAt = now()->toDateTimeImmutable();
    $firstEvidence->settledAt = now()->toDateTimeImmutable();
    $this->paymentAdapter->fundingObservation = $firstEvidence;
    $first = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    $data = exactPaymentObservation($attempt, amountMinor: 5000);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $observed = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, 5000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $observed->id, 'reason_code' => 'payment_attribution_or_eligibility',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('x_change_payment_review_attempts')->insert(['review_id' => $reviewId, 'payment_attempt_id' => $attempt->id]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $collection = app(ApplyReviewedSplitPayment::class)->handle($reviewId, $attempt->id, $issuer, 'Verified the later payment', 3000, 2000);
    expect($collection->collected_amount_minor)->toBe(3000)
        ->and($attempt->fresh()->voucher_collection_id)->toBe($first->voucher_collection_id)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(2)
        ->and(app(VoucherCollectionProgressService::class)->compute($voucher->fresh())->remaining_to_collect_minor)->toBe(0);
});

it('routes only the collected portion of a reviewed split to the voucher destination', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 100, 'max_amount' => 5000000]);
    $issuer = actingAsTestUser();
    fundTestUserClientFunds($issuer, 10000);
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'voucher_type' => 'payable', 'target_amount' => 100,
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']],
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $issuer->id, 'collection_wallet_id' => $issuer->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher->refresh());
    $data = exactPaymentObservation($attempt, amountMinor: 12000);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, 12000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'payment_attribution_or_eligibility',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('x_change_payment_review_attempts')->insert(['review_id' => $reviewId, 'payment_attempt_id' => $attempt->id]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $principal = app(TreasuryPrincipalReferenceResolverContract::class)->resolve($issuer);
    $balance = fn (): int => collect(app(TreasuryPositionReadModelContract::class)->forPrincipal($principal))
        ->first(fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds)?->balanceMinor ?? 0;
    $before = $balance();
    $collection = app(ApplyReviewedSplitPayment::class)->handle($reviewId, $attempt->id, $issuer, 'Verified the full payment', 10000, 2000);
    $payout = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->where('claim_type', 'value_flow')->sole();
    expect($collection->collected_amount_minor)->toBe(10000)
        ->and((int) data_get($payout->meta, 'amount_minor'))->toBe(10000)
        ->and($balance())->toBe($before + 2000)
        ->and((int) DB::table('treasury_inventories')->where('inventory_reference', 'inventory:netbank:vca-cash')->value('balance_minor'))->toBe(12000);
});

it('serializes concurrent split decisions against the same provider payment', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $issuer = actingAsTestUser();
    fundTestUserClientFunds($issuer, 10000);
    $voucher = paymentAttemptCollectibleVoucherForUser($issuer);
    $attempt = issuedPaymentAttempt($voucher);
    $data = exactPaymentObservation($attempt, amountMinor: 12000);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $transaction = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, 12000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $reviewId = DB::table('x_change_payment_transaction_reviews')->insertGetId([
        'observed_payment_transaction_id' => $transaction->id, 'reason_code' => 'payment_attribution_or_eligibility',
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('x_change_payment_review_attempts')->insert(['review_id' => $reviewId, 'payment_attempt_id' => $attempt->id]);
    $adapter = new SpecificPaymentTestAdapter;
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $children = $channels = $paths = [];
    DB::purge();
    for ($i = 0; $i < 2; $i++) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $path = tempnam(sys_get_temp_dir(), 'split-race-');
        if ($channel === false || $path === false || ($pid = pcntl_fork()) === -1) {
            throw new RuntimeException('Unable to start concurrent split review.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            foreach (array_keys(app()->getBindings()) as $binding) {
                if (str_starts_with($binding, 'Bavix\\Wallet\\')) {
                    app()->forgetInstance($binding);
                }
            }
            fread($channel[1], 1);
            try {
                $result = app(ApplyReviewedSplitPayment::class)->handle($reviewId, $attempt->id, $issuer, 'Verified split payment', 10000, 2000);
                file_put_contents($path, (string) $result->id);
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($path, get_class($exception).': '.$exception->getMessage());
                exit(1);
            }
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $paths[] = $path;
    }
    foreach ($channels as $channel) {
        fwrite($channel, 'x');
        fclose($channel);
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }
    DB::purge();
    $results = array_map(fn (string $path): string => trim((string) file_get_contents($path)), $paths);
    foreach ($paths as $path) {
        unlink($path);
    }
    expect($results[0])->toMatch('/^\d+$/')->and($results[1])->toBe($results[0])
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(DB::table('x_change_reviewed_payment_splits')->where('review_id', $reviewId)->count())->toBe(1);
});

it('denies both operator payment decisions to unauthorized users', function (): void {
    $user = actingAsTestUser();
    Gate::define('x-change.recovery.manage', fn ($actor): bool => false);
    $this->actingAs($user)->post('/x/operations/recovery/payments/1/credit-excess', [
        'reason' => 'Checked provider evidence', 'confirmed' => true,
    ])->assertForbidden();
    $this->actingAs($user)->post('/x/operations/recovery/payments/1/apply', [
        'reason' => 'Checked provider evidence', 'confirmed' => true, 'attempt_id' => 1,
    ])->assertForbidden();
    $this->actingAs($user)->post('/x/operations/recovery/payments/1/split', [
        'reason' => 'Checked provider evidence', 'confirmed' => true, 'attempt_id' => 1,
        'expected_collection_minor' => 10000, 'expected_excess_minor' => 2000,
    ])->assertForbidden();
});

it('monitors multiple credits on a settled QR without invoking settlement', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'monitor-settled-session', 'monitor-settled-request',
    ));
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['status' => PaymentAttemptStatus::Settled]);
    $attempt = $attempt->fresh();
    $this->paymentAdapter->incomingPayments = [
        new ObservedPaymentTransactionData('incoming-1', 2500, 'PHP', 'settled'),
        new ObservedPaymentTransactionData('incoming-2', 5000, 'PHP', 'settled'),
    ];

    $first = app(MonitorPaymentAttempt::class)->handle($attempt);
    $replay = app(MonitorPaymentAttempt::class)->handle($attempt);

    expect($first)->toBe(2)
        ->and($replay)->toBe(2)
        ->and($this->paymentAdapter->incomingPaymentCalls)->toBe(2)
        ->and(ObservedPaymentTransaction::query()->count())->toBe(2)
        ->and($attempt->fresh()->last_monitored_at)->not->toBeNull()
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(DB::table('voucher_collections')->count())->toBe(0);
});

it('settles a second verified transaction on the same QR once', function (): void {
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 60,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $user->id, 'collection_wallet_id' => $user->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher);
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['expected_amount_minor' => 2000]);
    $attempt = $attempt->fresh();
    $initial = exactPaymentObservation($attempt);
    $initial->occurredAt = now()->toDateTimeImmutable();
    $initial->settledAt = now()->toDateTimeImmutable();
    $this->paymentAdapter->fundingObservation = $initial;
    $attempt = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    $balance = (float) $user->wallet->fresh()->balanceFloat;
    $data = exactPaymentObservation($attempt);
    $data->occurredAt = now()->toDateTimeImmutable();
    $data->settledAt = now()->toDateTimeImmutable();
    $seen = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
        $data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    ));
    $observation = app(RecordProviderFundingObservation::class)->handle($data);
    $action = app(SettleAdditionalQrPayment::class);
    $collection = $action->handle($seen, $observation);
    $replay = $action->handle($seen, $observation);

    expect($collection->is($replay))->toBeTrue()
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(2)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balance + 20.00);
    foreach ([3, 4] as $number) {
        $data = exactPaymentObservation($attempt);
        $data->occurredAt = now()->toDateTimeImmutable();
        $data->settledAt = now()->toDateTimeImmutable();
        $seen = app(RecordObservedPaymentTransaction::class)->handle($attempt, new ObservedPaymentTransactionData(
            $data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
        ));
        $observation = app(RecordProviderFundingObservation::class)->handle($data);
        if ($number === 3) {
            $action->handle($seen, $observation);
        } else {
            expect(fn () => $action->handle($seen, $observation))
                ->toThrow(LatePaymentRequiresReview::class);
        }
    }
    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(3)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balance + 40.00);
});

it('individually verifies and collects monitored QR transactions through the provider capability', function (): void {
    Queue::fake();
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 60,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $user->id, 'collection_wallet_id' => $user->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher);
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['expected_amount_minor' => 2000]);
    $attempt = $attempt->fresh();
    $initial = exactPaymentObservation($attempt);
    $initial->occurredAt = now()->toDateTimeImmutable();
    $initial->settledAt = now()->toDateTimeImmutable();
    $this->paymentAdapter->fundingObservation = $initial;
    $attempt = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    $adapter = new SpecificPaymentTestAdapter;
    foreach ([1, 2, 3] as $number) {
        $data = exactPaymentObservation($attempt);
        $data->occurredAt = now()->toDateTimeImmutable();
        $data->settledAt = now()->toDateTimeImmutable();
        $adapter->specificObservations[$data->providerTransactionId] = $data;
        $adapter->incomingPayments[] = new ObservedPaymentTransactionData(
            $data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
        );
    }
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorPaymentAttempt::class)->handle($attempt);
    Queue::assertPushed(VerifyAdditionalQrPaymentJob::class, 3);
    foreach ($attempt->observedPayments()->get() as $transaction) {
        app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
        app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    }

    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(3)
        ->and(DB::table('x_change_payment_transaction_reviews')->count())->toBe(1);
});

it('collects two payments observed before the first QR verification without replaying either', function (bool $initiallyAmbiguous): void {
    Queue::fake();
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 60,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $user->id, 'collection_wallet_id' => $user->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher);
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['expected_amount_minor' => 2000]);
    $attempt = $attempt->fresh();
    if ($initiallyAmbiguous) {
        $attempt = app(TransitionPaymentAttempt::class)->handle(
            $attempt, PaymentAttemptStatus::Suspense, 'provider_verification_indeterminate', PaymentVerificationTrigger::Payer,
        );
    }
    $adapter = new SpecificPaymentTestAdapter;
    foreach ([1, 2] as $number) {
        $data = exactPaymentObservation($attempt);
        $data->occurredAt = now()->toDateTimeImmutable();
        $data->settledAt = now()->toDateTimeImmutable();
        $adapter->specificObservations[$data->providerTransactionId] = $data;
        $adapter->incomingPayments[] = new ObservedPaymentTransactionData(
            $data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
        );
    }
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorPaymentAttempt::class)->handle($attempt);
    Queue::assertPushed(VerifyAdditionalQrPaymentJob::class, 2);
    foreach ($attempt->observedPayments()->get() as $transaction) {
        app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
        app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    }
    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(2)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->sum('collected_amount_minor'))->toBe(4000)
        ->and(DB::table('x_change_payment_transaction_reviews')->count())->toBe(0);
})->with([false, true]);

it('sends a same-amount payment for review when another QR could claim it', function (): void {
    Queue::fake();
    $voucher = paymentAttemptCollectibleVoucher();
    $first = issuedPaymentAttempt($voucher);
    $second = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'other-payer-session', 'other-payer-request',
    ));
    $adapter = new SpecificPaymentTestAdapter;
    $data = exactPaymentObservation($first);
    $data->occurredAt = now()->addSecond()->toDateTimeImmutable();
    $data->settledAt = now()->addSecond()->toDateTimeImmutable();
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $adapter->incomingPayments = [new ObservedPaymentTransactionData(
        $data->providerTransactionId, $first->expected_amount_minor, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt,
    )];
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorPaymentAttempt::class)->handle($first);
    $transaction = $first->observedPayments()->sole();
    expect(app(MonitorPaymentAttempt::class)->handle($second))->toBe(1);
    app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);

    expect($second->expected_amount_minor)->toBe($first->expected_amount_minor)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(0)
        ->and(DB::table('x_change_payment_transaction_reviews')->where('observed_payment_transaction_id', $transaction->id)->value('reason_code'))->toBe('payment_observed_on_multiple_qrs')
        ->and(app(SessionPaymentReview::class)->exists($voucher, 'payer-session-1'))->toBeTrue()
        ->and(app(SessionPaymentReview::class)->exists($voucher, 'other-payer-session'))->toBeTrue()
        ->and(app(SessionPaymentReview::class)->exists($voucher, 'unrelated-session'))->toBeFalse();
});

it('serializes two different provider payments against the same QR on a real database', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    Queue::fake();
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'target_amount' => 60,
        'metadata' => ['flow_type' => 'collectible', 'issuer_id' => (string) $user->id, 'collection_wallet_id' => $user->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher);
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['expected_amount_minor' => 2000]);
    $attempt = $attempt->fresh();
    $adapter = new SpecificPaymentTestAdapter;
    foreach ([1, 2] as $number) {
        $data = exactPaymentObservation($attempt);
        $data->occurredAt = now()->toDateTimeImmutable();
        $data->settledAt = now()->toDateTimeImmutable();
        $adapter->specificObservations[$data->providerTransactionId] = $data;
        $adapter->incomingPayments[] = new ObservedPaymentTransactionData($data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt);
    }
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorPaymentAttempt::class)->handle($attempt);
    $ids = $attempt->observedPayments()->pluck('id')->all();
    $children = [];
    $channels = [];
    $paths = [];
    foreach ($ids as $id) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $path = tempnam(sys_get_temp_dir(), 'payment-race-');
        if ($channel === false || $path === false) {
            throw new RuntimeException('Unable to prepare the payment race.');
        }
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            fread($channel[1], 1);
            try {
                app()->call([new VerifyAdditionalQrPaymentJob($id, 'netbank'), 'handle']);
                file_put_contents($path, 'ok');
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($path, get_class($exception).': '.$exception->getMessage());
                exit(1);
            }
        }
        if ($pid < 0) {
            throw new RuntimeException('Unable to start the payment race.');
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $paths[] = $path;
    }
    foreach ($channels as $channel) {
        fwrite($channel, '1');
        fclose($channel);
    }
    $codes = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $codes[] = pcntl_wexitstatus($status);
    }
    $results = array_map(fn (string $path): string => (string) file_get_contents($path), $paths);
    foreach ($paths as $path) {
        unlink($path);
    }
    DB::purge();
    expect($codes)->toBe([0, 0], implode('; ', $results))
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(2)
        ->and((int) VoucherCollection::query()->where('voucher_id', $voucher->id)->sum('collected_amount_minor'))->toBe(4000);
});

it('waits for an incoming pending payment and follows the provider outcome', function (bool $settles): void {
    Queue::fake();
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $adapter = new SpecificPaymentTestAdapter;
    $data = exactPaymentObservation($attempt, status: 'pending', settledAt: null);
    $data->occurredAt = now()->toDateTimeImmutable();
    $adapter->specificObservations[$data->providerTransactionId] = $data;
    $adapter->incomingPayments = [new ObservedPaymentTransactionData($data->providerTransactionId, $attempt->expected_amount_minor, 'PHP', 'pending', occurredAt: $data->occurredAt)];
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorPaymentAttempt::class)->handle($attempt);
    $transaction = $attempt->observedPayments()->sole();
    app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    expect(VoucherCollection::query()->count())->toBe(0)->and(DB::table('x_change_payment_transaction_reviews')->count())->toBe(0);
    $data->providerStatus = $settles ? 'settled' : 'rejected';
    $data->settledAt = $settles ? now()->toDateTimeImmutable() : null;
    $data->payloadHash = hash('sha256', 'settled-payload');
    $adapter->incomingPayments = [new ObservedPaymentTransactionData($data->providerTransactionId, $attempt->expected_amount_minor, 'PHP', $data->providerStatus, occurredAt: $data->occurredAt, settledAt: $data->settledAt)];
    app(MonitorPaymentAttempt::class)->handle($attempt->fresh());
    app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    expect(VoucherCollection::query()->count())->toBe($settles ? 1 : 0)
        ->and($attempt->fresh()->status)->toBe($settles ? PaymentAttemptStatus::Settled : PaymentAttemptStatus::AwaitingPayment)
        ->and(DB::table('x_change_payment_transaction_reviews')->count())->toBe($settles ? 0 : 1)
        ->and($transaction->statuses()->pluck('provider_status')->all())->toBe(['pending', $data->providerStatus]);
})->with([true, false]);

it('recovers a rejected routed installment after two payments against one QR', function (): void {
    Queue::fake();
    enableNetbankTreasuryForTests();
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', ['enabled' => true, 'min_amount' => 100, 'max_amount' => 5_000_000]);
    $user = actingAsTestUser();
    fundTestUserClientFunds($user, 10_000);
    $inventory = app(TreasuryInventoryOperationContract::class);
    $inventory->registerInventory(new TreasuryInventoryData(inventoryReference: 'inventory:netbank:vca-cash', resourceType: 'cash_at_bank', currency: 'PHP', capacityMinor: 0, status: 'requested', idempotencyKey: 'register:qr-recovery', externalReference: 'resource:netbank:corporate-vca'));
    $inventory->recognize(new TreasuryInventoryRecognitionData(operationReference: 'qr-recovery:funding', inventoryReference: 'inventory:netbank:vca-cash', settlementResourceReference: 'resource:netbank:corporate-vca', amountMinor: 10_000, currency: 'PHP', status: 'requested', idempotencyKey: 'qr-recovery:funding', externalReference: 'qr-recovery:test'));
    $readiness = Mockery::mock(SettlementReadinessGateContract::class);
    $readiness->shouldReceive('ensureReady')->zeroOrMoreTimes();
    app()->instance(SettlementReadinessGateContract::class, $readiness);
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'voucher_type' => 'settlement', 'target_amount' => 60,
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'metadata' => ['settlement_workflow' => 'collect_for_settlement'], 'settlement' => ['driver' => 'x-change.documents', 'version' => '1.0.0', 'readiness_gate' => 'settleable'], 'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999']],
        'metadata' => ['flow_type' => 'settlement', 'issuer_id' => (string) $user->id, 'collection_wallet_id' => $user->wallet->id],
    ]));
    $attempt = issuedPaymentAttempt($voucher);
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['expected_amount_minor' => 2000]);
    $attempt = $attempt->fresh();
    $adapter = new SpecificPaymentTestAdapter;
    foreach ([1, 2] as $number) {
        $data = exactPaymentObservation($attempt);
        $data->occurredAt = now()->toDateTimeImmutable();
        $data->settledAt = now()->toDateTimeImmutable();
        $adapter->specificObservations[$data->providerTransactionId] = $data;
        $adapter->incomingPayments[] = new ObservedPaymentTransactionData($data->providerTransactionId, 2000, 'PHP', 'settled', occurredAt: $data->occurredAt, settledAt: $data->settledAt);
    }
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    app(MonitorPaymentAttempt::class)->handle($attempt);
    foreach ($attempt->observedPayments()->get() as $transaction) {
        app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
        app()->call([new VerifyAdditionalQrPaymentJob($transaction->getKey(), 'netbank'), 'handle']);
    }
    $payouts = DisbursementReconciliation::query()->where('voucher_id', $voucher->id)->where('claim_type', 'value_flow')->orderBy('id')->get();
    expect($payouts)->toHaveCount(2);
    $service = app(ValueFlowPayoutService::class);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(transactionId: 'first-payout', provider: 'netbank');
    $service->submit($payouts[0]->id);
    $provider->willReturnFailedResult(transactionId: 'rejected-payout', provider: 'netbank');
    $service->submit($payouts[1]->id);
    $provider->nextCheckStatus = PayoutStatus::FAILED;
    $retry = $service->retryRejectedPayout($payouts[1]->id, 'recipient-confirmed-ready', 'operator:test');
    $provider->willReturnSuccessfulResult(transactionId: 'recovered-payout', provider: 'netbank');
    $service->submit($retry->id);
    $service->submit($retry->id);

    expect($payouts[0]->fresh()->internal_status)->toBe('finalized')
        ->and($payouts[1]->fresh()->internal_status)->toBe('recovery_retried')
        ->and($retry->fresh()->internal_status)->toBe('finalized')
        ->and($provider->disburseCallCount)->toBe(3)
        ->and((int) VoucherCollection::query()->where('voucher_id', $voucher->id)->sum('collected_amount_minor'))->toBe(4000)
        ->and(app(VoucherCollectionProgressService::class)->compute($voucher->fresh())->remaining_to_collect_minor)->toBe(2000);
});

it('integrates the local NetBank reader with x-change payment persistence', function (): void {
    Queue::fake();
    expect(method_exists(NetbankFundingProviderAdapter::class, 'incomingPayments'))->toBeTrue();

    config()->set('payment-gateway.netbank.funding.corporate_account_number', '113-001-00001-9');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'X-Change');
    config()->set('payment-gateway.netbank.funding.vca_alias', '91500');
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'reader-session', 'reader-request',
    ));
    $vca = $attempt->funding_address_ciphertext;
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('allTransactions')->once()->with($vca, '113-001-00001-9')
        ->andReturn((function () use ($vca): Generator {
            foreach ([['credit-1', 2500], ['credit-2', 5000]] as [$id, $amount]) {
                yield [
                    'transaction_id' => $id,
                    'type' => 'Credit',
                    'description' => 'EXTERNAL_TRANSFER_INCOMING',
                    'destination_account' => ['account_alias' => $vca],
                    'amount' => ['num' => (string) $amount, 'cur' => 'PHP'],
                    'status' => 'SETTLED',
                    'date' => now()->toIso8601String(),
                    'sender_name' => 'Apple Hurtado',
                    'source_account' => ['account_number' => '09175180722', 'bank_code' => 'GXCHPHM2XXX'],
                ];
            }
        })());
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([
        new NetbankFundingProviderAdapter($client),
    ]));

    expect(app(MonitorPaymentAttempt::class)->handle($attempt))->toBe(2)
        ->and($attempt->observedPayments()->count())->toBe(2)
        ->and($attempt->observedPayments()->firstOrFail()->payer_mobile_ciphertext)->toBeNull()
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and(DB::table('voucher_collections')->count())->toBe(0);
    Queue::assertPushed(VerifyAdditionalQrPaymentJob::class, 2);
});

it('does not call NetBank after the QR observation window closes', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(IssuePaymentInstructions::class)->handle(app(CreatePaymentAttempt::class)->handle(
        $voucher, 'netbank', 'monitor-expired-session', 'monitor-expired-request',
    ));
    PaymentAttempt::query()->whereKey($attempt->getKey())->update(['expires_at' => now()->subDays(8)]);

    expect(app(MonitorPaymentAttempt::class)->handle($attempt->fresh()))->toBe(0)
        ->and($this->paymentAdapter->incomingPaymentCalls)->toBe(0);
});

it('keeps payer identity out of the private broadcast payload', function (): void {
    $payment = new ObservedPaymentTransactionData(
        'private-transaction', 2500, 'PHP', 'settled',
        payerName: 'Apple Hurtado', payerAccountNumber: '09175180722',
    );
    $event = new PaymentTransactionObserved('App\\Models\\User', '123', new PaymentObservationNoticeData(
        1, 2, 3, $payment->providerStatus, 'provider_payment_observed', now()->toIso8601String(),
    ));
    $payload = json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);

    expect($payload)->not->toContain('Apple Hurtado')
        ->not->toContain('09175180722')
        ->not->toContain('private-transaction');

    expect(json_encode($event, JSON_THROW_ON_ERROR))->not->toContain('Apple Hurtado')
        ->not->toContain('09175180722')
        ->not->toContain('private-transaction');
});

it('creates an exact Payment Attempt for the collectible balance', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();

    recordPaymentAttemptCollection($voucher, 25.00);

    $attempt = app(CreatePaymentAttempt::class)->handle(
        voucher: $voucher,
        provider: 'netbank',
        browserKey: 'payer-session-1',
        idempotencyKey: 'request-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and($attempt->expected_amount_minor)->toBe(7500)
        ->and($attempt->currency)->toBe('PHP')
        ->and($attempt->session_key_hash)->toHaveLength(64)
        ->and($attempt->session_key_hash)->not->toContain('payer-session-1')
        ->and($attempt->events)->toHaveCount(1)
        ->and($attempt->events->first()->event_type)->toBe('created');
});

it('replays creation idempotently without creating a second attempt', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $create = app(CreatePaymentAttempt::class);

    $first = $create->handle($voucher, 'netbank', 'payer-session-1', 'request-1');
    $replay = $create->handle($voucher, 'netbank', 'payer-session-1', 'request-1');

    expect($replay->is($first))->toBeTrue()
        ->and(PaymentAttempt::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1);
});

it('issues encrypted provider QR instructions once without Account funding', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-1',
        'request-1',
    );

    $issued = app(IssuePaymentInstructions::class)->handle($attempt);
    $retry = app(IssuePaymentInstructions::class)->handle($attempt);
    $raw = DB::table('x_change_payment_attempts')->find($attempt->getKey());

    expect($issued->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($issued->version)->toBe(2)
        ->and($issued->instructions_ciphertext['qr_code'])->toMatchArray([
            'mime_type' => 'image/png',
            'qr_mode' => 'dynamic',
            'transaction_type' => 'p2m',
            'embedded_amount' => true,
            'provider_generated' => true,
        ])
        ->and($issued->provider_request_id_ciphertext)->toBe('915001234567890123456')
        ->and($raw->provider_request_id_ciphertext)->not->toContain('915001234567890123456')
        ->and($raw->instructions_ciphertext)->not->toContain('iVBORw0KGgo')
        ->and($retry->getKey())->toBe($issued->getKey())
        ->and($retry->events)->toHaveCount(2)
        ->and($this->paymentAdapter->instructionCalls)->toBe(1)
        ->and(DB::table('x_change_funding_intents')->count())->toBe(0)
        ->and(DB::table('x_change_account_funding_receipts')->count())->toBe(0);
});

it('uses the optional direct QR capability without a concrete provider class', function (): void {
    $adapter = new class extends FakeFundingProviderAdapter implements DirectQrFundingInstructionIssuer
    {
        public int $directCalls = 0;

        public function createDirectQrFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
        {
            $this->directCalls++;

            return $this->createFundingInstructions($request);
        }
    };
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$adapter]));
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = app(CreatePaymentAttempt::class)->handle($voucher, 'netbank', 'payer-session-1', 'request-1');

    $issued = app(IssuePaymentInstructions::class)->handle($attempt);

    expect($issued->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($adapter->directCalls)->toBe(1);
});

it('issues a provisional NetBank payer QR with one provider call and no VCA registration', function (): void {
    config()->set('payment-gateway.netbank.funding.reference_key', 'test-payment-reference-key');
    config()->set('payment-gateway.netbank.funding.pre_transaction_validation_enabled', true);
    config()->set('payment-gateway.netbank.funding.exact_limits_enabled', true);
    $qrPayload = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==';
    $user = actingAsTestUser();
    app(MerchantProfileRepositoryContract::class)->updateForUser($user, [
        'name' => 'Lester Store',
        'city' => 'Makati',
        'merchant_category_code' => '5999',
        'merchant_name_template' => '{name}',
    ]);
    $expectedMerchant = app(FundingQrMerchantProfileResolver::class)->resolve($user);
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldNotReceive('generateAliasToken');
    $client->shouldNotReceive('registerPreTransactionReference');
    $client->shouldNotReceive('createExactLimit');
    $client->shouldReceive('generateQrCode')
        ->once()
        ->withArgs(fn (string $vcaNumber, int $amountMinor, string $currency, FundingQrMerchantData $merchant): bool => preg_match('/\A91500\d{16}\z/', $vcaNumber) === 1
            && $amountMinor === 10000
            && $currency === 'PHP'
            && FundingMerchantSnapshot::fromData($merchant) === FundingMerchantSnapshot::fromData($expectedMerchant))
        ->andReturn($qrPayload);

    $this->app->instance(NetbankFundingApiClient::class, $client);
    $this->app->instance(
        FundingProviderAdapterRegistry::class,
        new FundingProviderAdapterRegistry([
            new NetbankFundingProviderAdapter($client),
        ]),
    );

    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-provisional',
        'request-provisional',
    );

    $issued = app(IssuePaymentInstructions::class)->handle($attempt);
    $replay = app(IssuePaymentInstructions::class)->handle($attempt);
    $raw = DB::table('x_change_payment_attempts')->find($attempt->getKey());

    expect($issued->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($issued->expected_amount_minor)->toBe(10000)
        ->and($issued->currency)->toBe('PHP')
        ->and($issued->provider_request_id_ciphertext)->toMatch('/\A91500\d{16}\z/')
        ->and($issued->funding_address_ciphertext)->toBe($issued->provider_request_id_ciphertext)
        ->and($issued->merchant_snapshot_ciphertext)->toBe(FundingMerchantSnapshot::fromData($expectedMerchant))
        ->and($issued->merchant_profile_fingerprint)->toBe($expectedMerchant->profileFingerprint)
        ->and($raw->merchant_snapshot_ciphertext)->not->toContain('Lester Store')
        ->and($replay->merchant_snapshot_ciphertext)->toBe($issued->merchant_snapshot_ciphertext)
        ->and($replay->events)->toHaveCount(2)
        ->and($issued->instructions_ciphertext['qr_code'])->toMatchArray([
            'mime_type' => 'image/png',
            'base64_payload' => $qrPayload,
            'qr_mode' => 'dynamic',
            'transaction_type' => 'p2m',
            'embedded_amount' => true,
            'provider_generated' => true,
        ]);
});

it('uses an updated merchant profile only for newly issued Payment Attempts', function (): void {
    $user = actingAsTestUser();
    $profiles = app(MerchantProfileRepositoryContract::class);
    $profiles->updateForUser($user, [
        'name' => 'First Store',
        'city' => 'Makati',
        'merchant_category_code' => '5999',
        'merchant_name_template' => '{name}',
    ]);
    $firstVoucher = paymentAttemptCollectibleVoucherForUser($user);
    $first = app(IssuePaymentInstructions::class)->handle(
        app(CreatePaymentAttempt::class)->handle(
            $firstVoucher,
            'netbank',
            'first-payer-session',
            'first-request',
        ),
    );
    $firstSnapshot = $first->merchant_snapshot_ciphertext;

    $profiles->updateForUser($user, [
        'name' => 'Second Store',
        'city' => 'Pasig',
        'merchant_category_code' => '5812',
        'merchant_name_template' => '{name}',
    ]);
    $secondVoucher = paymentAttemptCollectibleVoucherForUser($user);
    $second = app(IssuePaymentInstructions::class)->handle(
        app(CreatePaymentAttempt::class)->handle(
            $secondVoucher,
            'netbank',
            'second-payer-session',
            'second-request',
        ),
    );

    expect($first->fresh()->merchant_snapshot_ciphertext)->toBe($firstSnapshot)
        ->and($firstSnapshot['displayName'])->toBe('First Store')
        ->and($firstSnapshot['city'])->toBe('Makati')
        ->and($second->merchant_snapshot_ciphertext['displayName'])->toBe('Second Store')
        ->and($second->merchant_snapshot_ciphertext['city'])->toBe('Pasig')
        ->and($second->merchant_profile_fingerprint)->not->toBe($first->merchant_profile_fingerprint)
        ->and($this->paymentAdapter->instructionCalls)->toBe(2);
});

it('fails closed before provider instructions when the merchant profile is inactive', function (): void {
    $user = actingAsTestUser();
    $profile = app(MerchantProfileRepositoryContract::class)->findOrCreateForUser($user);
    $profile->forceFill(['is_active' => false])->save();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'inactive-merchant-session',
        'inactive-merchant-request',
    );

    expect(fn () => app(IssuePaymentInstructions::class)->handle($attempt))
        ->toThrow(RuntimeException::class, 'Payment instructions are temporarily unavailable.');

    $failure = $attempt->fresh()->events()->where('event_type', 'provider_instruction_failed')->sole();

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::PendingInstructions)
        ->and($failure->metadata)->toBe([
            'provider' => 'netbank',
            'retryable' => true,
            'failure_stage' => 'merchant_profile',
        ])
        ->and($this->paymentAdapter->instructionCalls)->toBe(0);
});

it('settles one exact provider observation into one voucher collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);

    $settled = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );
    $replay = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($settled->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($settled->voucher_collection_id)->not->toBeNull()
        ->and($replay->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and(VoucherCollection::query()->findOrFail($settled->voucher_collection_id)->only([
            'status',
            'collected_amount_minor',
            'currency',
            'provider',
            'idempotency_key',
        ]))->toBe([
            'status' => 'collected',
            'collected_amount_minor' => 10000,
            'currency' => 'PHP',
            'provider' => 'netbank',
            'idempotency_key' => 'payment-attempt:'.$settled->reference,
        ])
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(DB::table('x_change_funding_intents')->count())->toBe(0)
        ->and(DB::table('x_change_account_funding_receipts')->count())->toBe(0);
});

it('replays an already-settled Payment Attempt without executing another collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);

    $settled = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );
    $balanceAfterSettlement = (float) $user->wallet->fresh()->balanceFloat;
    $eventCount = $settled->events()->count();
    $engine = Mockery::mock(ExecutionEngine::class);
    $engine->shouldNotReceive('execute');
    app()->instance(ExecutionEngine::class, $engine);

    $replay = app(SettleVerifiedPaymentAttempt::class)->handle(
        $settled,
        PaymentVerificationTrigger::Payer,
    );

    expect($replay->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($replay->voucher_collection_id)->toBe($settled->voucher_collection_id)
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and($replay->events()->count())->toBe($eventCount)
        ->and($balanceAfterSettlement)->toBe($balanceBefore + 100.00)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceAfterSettlement);
});

it('atomically emits one partner event from settlement and none on replay', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $client = PartnerApiClient::query()->create([
        'reference' => 'settlement-partner', 'oauth_client_id' => 'fake-client', 'name' => 'Fake partner',
        'issuer_type' => $user->getMorphClass(), 'issuer_id' => (string) $user->id,
        'status' => 'active', 'activated_at' => now(),
    ]);
    PartnerApiPayCodeReference::query()->create([
        'partner_api_client_id' => $client->id, 'voucher_id' => $voucher->id,
        'external_reference' => 'fake-settlement-reference', 'terms_hash' => hash('sha256', 'test'),
    ]);
    config()->set('x-change.partner_api.payment_events.enabled', true);
    config()->set('x-change.partner_api.payment_events.receivers.settlement-partner', []);
    Http::preventStrayRequests();
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
    app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule);
    app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule);
    $events = PartnerPaymentEvent::query()->get();
    expect($events)->toHaveCount(1);
    expect(json_decode($events->first()->body, true))->toMatchArray([
        'amount_minor' => 10000, 'external_reference' => 'fake-settlement-reference',
        'pay_code' => $voucher->code, 'partner_reference' => 'settlement-partner',
    ]);
    Http::assertNothingSent();
});

it('keeps pending provider history awaiting payment without collection', function (): void {
    $user = actingAsTestUser();
    $voucher = paymentAttemptCollectibleVoucherForUser($user);
    $balanceBefore = (float) $user->wallet->balanceFloat;
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation(
        $attempt,
        status: 'pending',
        settledAt: null,
    );

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::AwaitingPayment)
        ->and($checked->last_checked_at)->not->toBeNull()
        ->and(VoucherCollection::query()->count())->toBe(0)
        ->and((float) $user->wallet->fresh()->balanceFloat)->toBe($balanceBefore);
});

it('moves mismatched authoritative payment evidence to suspense', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation(
        $attempt,
        amountMinor: 9900,
    );

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Payer,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::Suspense)
        ->and($checked->events->last()->event_type)->toBe('provider_payment_mismatch')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('expires an unpaid attempt after the configured settlement grace', function (): void {
    config()->set('x-change.payment.attempts.settlement_grace_seconds', 300);
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->travelTo($attempt->expires_at->addMinutes(6));

    $checked = app(VerifyPaymentAttempt::class)->handle(
        $attempt,
        PaymentVerificationTrigger::Schedule,
    );

    expect($checked->status)->toBe(PaymentAttemptStatus::Expired)
        ->and($checked->expired_at)->not->toBeNull()
        ->and($checked->events->last()->event_type)->toBe('payment_attempt_expired')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('settles an on-time payment discovered after the QR expired exactly once', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $observation = exactPaymentObservation($attempt);
    $observation->occurredAt = DateTimeImmutable::createFromInterface($attempt->instructions_created_at->addMinute());
    $observation->settledAt = DateTimeImmutable::createFromInterface($attempt->expires_at->addMinutes(5));
    $this->travelTo($attempt->expires_at->addMinutes(6));
    $expired = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule);
    expect($expired->status)->toBe(PaymentAttemptStatus::Expired);

    $this->paymentAdapter->fundingObservation = $observation;
    $checked = app(VerifyPaymentAttempt::class)->handle($expired, PaymentVerificationTrigger::Schedule);
    $replayed = app(VerifyPaymentAttempt::class)->handle($checked, PaymentVerificationTrigger::Schedule);

    expect($checked->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($replayed->status)->toBe(PaymentAttemptStatus::Settled)
        ->and(VoucherCollection::query()->count())->toBe(1);
});

it('holds a payment made after QR expiry during the settlement grace period for review', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $observation = exactPaymentObservation($attempt);
    $observation->occurredAt = DateTimeImmutable::createFromInterface($attempt->expires_at->addSeconds(30));
    $observation->settledAt = DateTimeImmutable::createFromInterface($attempt->expires_at->addMinute());
    $this->travelTo($attempt->expires_at->addMinutes(2));
    $this->paymentAdapter->fundingObservation = $observation;

    expect(app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule)->status)
        ->toBe(PaymentAttemptStatus::Suspense)
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('holds a second active payment when the first has already met the target', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $first = issuedPaymentAttempt($voucher);
    $second = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($first);
    expect(app(VerifyPaymentAttempt::class)->handle($first, PaymentVerificationTrigger::Payer)->status)
        ->toBe(PaymentAttemptStatus::Settled);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($second);

    expect(app(VerifyPaymentAttempt::class)->handle($second, PaymentVerificationTrigger::Payer)->status)
        ->toBe(PaymentAttemptStatus::Suspense)
        ->and(VoucherCollection::query()->count())->toBe(1);
});

it('holds a payment made after QR expiry for review', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $observation = exactPaymentObservation($attempt);
    $observation->occurredAt = DateTimeImmutable::createFromInterface($attempt->expires_at->addMinute());
    $observation->settledAt = DateTimeImmutable::createFromInterface($attempt->expires_at->addMinutes(2));
    $this->travelTo($attempt->expires_at->addMinutes(6));
    $expired = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule);
    $this->paymentAdapter->fundingObservation = $observation;

    $checked = app(VerifyPaymentAttempt::class)->handle($expired, PaymentVerificationTrigger::Schedule);

    expect($checked->status)->toBe(PaymentAttemptStatus::Suspense)
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('holds late evidence without a transaction timestamp for review', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $observation = exactPaymentObservation($attempt);
    $observation->occurredAt = null;
    $this->travelTo($attempt->expires_at->addMinutes(6));
    $expired = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule);
    $this->paymentAdapter->fundingObservation = $observation;

    expect(app(VerifyPaymentAttempt::class)->handle($expired, PaymentVerificationTrigger::Schedule)->status)
        ->toBe(PaymentAttemptStatus::Suspense)
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('does not check provider history after the late reconciliation window', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->travelTo($attempt->expires_at->addMinutes(6));
    $expired = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule);
    $this->travelTo($attempt->expires_at->addDays(8));

    expect(app(VerifyPaymentAttempt::class)->handle($expired, PaymentVerificationTrigger::Schedule)->status)
        ->toBe(PaymentAttemptStatus::Expired)
        ->and($this->paymentAdapter->fundingVerifications)->toBeEmpty();
});

it('keeps an expired QR expired when no payment is observed', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $attempt = issuedPaymentAttempt($voucher);
    $this->travelTo($attempt->expires_at->addMinutes(6));
    $expired = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Schedule);
    $this->paymentAdapter->fundingVerificationResolver = static function (): never {
        throw new ProviderFundingNotObserved('no payment');
    };

    $checked = app(VerifyPaymentAttempt::class)->handle($expired, PaymentVerificationTrigger::Schedule);

    expect($checked->status)->toBe(PaymentAttemptStatus::Expired)
        ->and($checked->events->last()->event_type)->toBe('provider_payment_not_observed')
        ->and(VoucherCollection::query()->count())->toBe(0);
});

it('holds a late-discovered payment when another payment has already closed the voucher', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $first = issuedPaymentAttempt($voucher);
    $second = issuedPaymentAttempt($voucher);
    $this->paymentAdapter->fundingObservation = exactPaymentObservation($first);
    expect(app(VerifyPaymentAttempt::class)->handle($first, PaymentVerificationTrigger::Payer)->status)
        ->toBe(PaymentAttemptStatus::Settled);
    $late = exactPaymentObservation($second);
    $late->occurredAt = DateTimeImmutable::createFromInterface($second->instructions_created_at->addMinute());
    $late->settledAt = DateTimeImmutable::createFromInterface($second->expires_at->addMinutes(5));
    $this->travelTo($second->expires_at->addMinutes(6));
    $expired = app(VerifyPaymentAttempt::class)->handle($second, PaymentVerificationTrigger::Schedule);
    $this->paymentAdapter->fundingObservation = $late;

    $checked = app(VerifyPaymentAttempt::class)->handle($expired, PaymentVerificationTrigger::Schedule);

    expect($checked->status)->toBe(PaymentAttemptStatus::Suspense)
        ->and(VoucherCollection::query()->count())->toBe(1);
});

it('rolls back affected foreign key migrations on mysql', function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('Requires the dedicated MySQL test database.');
    }

    foreach ([
        '2026_10_12_000000_add_partner_client_to_x_change_checkouts.php',
        '2026_09_24_000000_add_settlement_collection_sources_to_campaign_payments.php',
        '2026_08_08_130000_add_commercial_partner_links_to_allocations_and_payout_batches.php',
        '2026_07_25_214455_add_voucher_id_to_x_change_funding_requests_table.php',
    ] as $migration) {
        (require __DIR__.'/../../../../database/migrations/'.$migration)->down();
    }

    expect(Schema::hasColumn('x_change_checkouts', 'partner_api_client_id'))->toBeFalse()
        ->and(Schema::hasColumn('x_change_campaign_payment_recognitions', 'campaign_payment_source_id'))->toBeFalse()
        ->and(Schema::hasColumn('x_change_partner_commission_payout_batches', 'commercial_partner_id'))->toBeFalse()
        ->and(Schema::hasColumn('x_change_funding_requests', 'voucher_id'))->toBeFalse();
});

it('serializes concurrent payments for the same voucher on a row-locking database', function (): void {
    $driver = DB::connection()->getDriverName();
    if (! in_array($driver, ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }

    expect(DB::connection()->transactionLevel())->toBe(0);
    $voucher = paymentAttemptCollectibleVoucher();
    $attempts = [issuedPaymentAttempt($voucher), issuedPaymentAttempt($voucher)];
    $expiryDetails = 'expires='.$attempts[0]->fresh()->expires_at?->toIso8601String().', now='.now()->toIso8601String();
    $children = [];
    $channels = [];
    $resultPaths = [];

    foreach ($attempts as $attempt) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $resultPath = tempnam(sys_get_temp_dir(), 'xchange-payment-race-');
        if ($channel === false || $resultPath === false) {
            throw new RuntimeException('Unable to prepare the payment concurrency test.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to start the payment concurrency test.');
        }

        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            fread($channel[1], 1);
            try {
                $this->paymentAdapter->fundingObservation = exactPaymentObservation($attempt);
                $result = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
                file_put_contents($resultPath, $result->status->value);
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($resultPath, 'error:'.get_class($exception).':'.$exception->getMessage());
                exit(1);
            }
        }

        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $resultPaths[] = $resultPath;
    }

    $configuration = config('database.connections.testing');
    $dsn = sprintf('%s:host=%s;port=%d;dbname=%s', $driver, $configuration['host'], $configuration['port'], $configuration['database']);
    $lock = new PDO($dsn, $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $observer = new PDO($dsn, $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $lock->beginTransaction();
    $statement = $lock->prepare('select id from vouchers where id = ? for update');
    $statement->execute([$voucher->getKey()]);
    foreach ($channels as $channel) {
        fwrite($channel, '1');
        fclose($channel);
    }

    $deadline = microtime(true) + 15;
    $bothVerified = false;
    try {
        $query = $observer->prepare('select count(*) from x_change_payment_attempts where voucher_id = ? and status = ?');
        do {
            $query->execute([$voucher->getKey(), PaymentAttemptStatus::Verified->value]);
            $bothVerified = (int) $query->fetchColumn() === 2;
            if (! $bothVerified) {
                usleep(50_000);
            }
        } while (! $bothVerified && microtime(true) < $deadline);
    } finally {
        $lock->commit();
    }

    $exitCodes = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $exitCodes[] = pcntl_wexitstatus($status);
    }
    $results = array_map(static fn (string $path): string => (string) file_get_contents($path), $resultPaths);
    foreach ($resultPaths as $path) {
        unlink($path);
    }
    DB::purge();
    sort($results);

    expect($bothVerified)->toBeTrue('Both workers must reach verified state while the voucher is locked; '.$expiryDetails.'; results: '.implode('; ', $results))
        ->and($exitCodes)->toBe([0, 0], implode('; ', $results))
        ->and($results)->toBe([PaymentAttemptStatus::Settled->value, PaymentAttemptStatus::Suspense->value])
        ->and(VoucherCollection::query()->where('voucher_id', $voucher->getKey())->count())->toBe(1);
});

it('accepts a lost-session report without exposing attempt state and encrypts its evidence', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $payload = [
        'transaction_reference' => 'PROVIDER-REFERENCE-123',
        'amount_minor' => 5000,
        'paid_at' => '2026-11-03T12:00:00+08:00',
        'contact' => 'payer@example.test',
    ];
    $response = $this->postJson(route('x-change.pay.recovery-reports.store', ['code' => $voucher->code]), $payload);
    $unknown = $this->postJson(route('x-change.pay.recovery-reports.store', ['code' => 'UNKNOWN-CODE']), $payload);

    $response->assertStatus(202)->assertJsonMissingPath('attempt');
    $unknown->assertStatus(202)->assertExactJson($response->json());
    $report = DB::table('x_change_payment_recovery_reports')->sole();
    expect($report->voucher_id)->toBe($voucher->getKey())
        ->and($report->evidence_ciphertext)->not->toContain('PROVIDER-REFERENCE-123')
        ->and(json_decode(Crypt::decryptString($report->evidence_ciphertext), true))->toMatchArray($payload)
        ->and(PaymentAttempt::query()->count())->toBe(0);
});

it('only allows the voucher owner to review its lost-session reports', function (): void {
    $voucher = paymentAttemptCollectibleVoucher();
    $owner = auth()->user();
    $payload = ['transaction_reference' => 'REFERENCE-XYZ', 'amount_minor' => 5000, 'paid_at' => '2026-11-03T12:00:00+08:00'];
    $this->postJson(route('x-change.pay.recovery-reports.store', ['code' => $voucher->code]), $payload)->assertStatus(202);
    $report = DB::table('x_change_payment_recovery_reports')->sole();
    $index = route('x-change.cockpit.pay-codes.payment-recovery-reports.index', ['code' => $voucher->code]);
    $review = route('x-change.cockpit.pay-codes.payment-recovery-reports.review', ['code' => $voucher->code, 'reference' => $report->reference]);
    actingAsTestUser();
    $this->getJson($index)->assertNotFound();
    $this->postJson($review, ['note' => 'reviewed'])->assertNotFound();
    $this->actingAs($owner);
    $this->getJson($index)->assertSuccessful()->assertJsonPath('reports.0.evidence.transaction_reference', 'REFERENCE-XYZ');
    $this->postJson($review, ['note' => 'Reviewed against provider records'])->assertSuccessful();
    $this->getJson($index)->assertSuccessful()->assertJsonPath('reports.0.review_note', 'Reviewed against provider records');
    $this->postJson($review, ['note' => 'Repeat'])->assertNotFound();
    $updated = DB::table('x_change_payment_recovery_reports')->where('id', $report->id)->first();
    expect($updated->status)->toBe('reviewed')
        ->and(Crypt::decryptString($updated->review_note_ciphertext))->toBe('Reviewed against provider records')
        ->and(PaymentAttempt::query()->count())->toBe(0);
});

function paymentAttemptCollectibleVoucher(): Voucher
{
    return paymentAttemptCollectibleVoucherForUser(actingAsTestUser());
}

function paymentAttemptCollectibleVoucherForUser(User $user): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,
            ],
        ],
    ));
}

function issuedPaymentAttempt(Voucher $voucher): PaymentAttempt
{
    $attempt = app(CreatePaymentAttempt::class)->handle(
        $voucher,
        'netbank',
        'payer-session-1',
        'request-'.str()->uuid(),
    );

    return app(IssuePaymentInstructions::class)->handle($attempt);
}

function exactPaymentObservation(
    PaymentAttempt $attempt,
    string $status = 'settled',
    ?DateTimeImmutable $settledAt = new DateTimeImmutable('2026-07-24T05:00:00+00:00'),
    ?int $amountMinor = null,
): ProviderFundingObservationData {
    $amountMinor ??= $attempt->expected_amount_minor;

    return new ProviderFundingObservationData(
        provider: $attempt->provider_code,
        providerTransactionId: 'payment-transaction-'.str()->uuid(),
        grossAmountMinor: $amountMinor,
        feeAmountMinor: 0,
        netAmountMinor: $amountMinor,
        currency: $attempt->currency,
        providerStatus: $status,
        verificationSource: 'fake-authoritative-vca-history',
        payloadHash: hash('sha256', 'payment-observation-'.str()->uuid()),
        fundingAddress: 'sha256:'.hash('sha256', (string) $attempt->funding_address_ciphertext),
        occurredAt: new DateTimeImmutable('2026-07-24T04:59:00+00:00'),
        settledAt: $settledAt,
        metadata: [
            'destination_verified' => true,
        ],
    );
}

function recordPaymentAttemptCollection(Voucher $voucher, float $amount): void
{
    app(RecordVoucherCollection::class)->handle(
        voucher: $voucher,
        result: new VoucherPaymentResultData(
            voucher_code: (string) $voucher->code,
            status: 'collected',
            amount: $amount,
            currency: 'PHP',
            provider: 'test',
            provider_reference: 'reference-'.str()->uuid(),
            provider_transaction_id: 'transaction-'.str()->uuid(),
        ),
        payload: [
            'amount' => $amount,
        ],
    );
}
