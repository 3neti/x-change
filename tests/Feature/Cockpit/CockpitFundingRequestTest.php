<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Funding\CreateFundingRequest;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Tests\Fakes\User;

it('lets an Account owner submit a request without accepting monetary authority', function () {
    $requester = actingAsTestUser(25_000);
    $balanceBefore = (int) $requester->wallet->balance;

    $this->post(route('x-change.cockpit.funding.requests.store'), [
        'funding_type' => 'BANK_TRANSFER',
        'requested_value_minor' => 2_000_000,
        'currency' => 'php',
        'description' => 'Large corporate transfer awaiting an authoritative bank match.',
        'external_reference' => 'BANK-2026-1001',
        'occurred_on' => '2026-07-25',
        'requester_notes' => 'Please verify against the configured corporate account.',
        'idempotency_key' => 'cockpit-funding-request-1001',
    ])->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas('funding_notice');

    $request = FundingRequest::query()->sole();

    expect($request->status)->toBe(FundingRequestStatus::Submitted)
        ->and($request->approved_value_minor)->toBeNull()
        ->and((int) $requester->wallet->fresh()->balance)->toBe($balanceBefore);

    $this->post(route('x-change.cockpit.funding.requests.store'), [
        'funding_type' => 'bank_transfer',
        'requested_value_minor' => 2_000_000,
        'currency' => 'PHP',
        'description' => 'Large corporate transfer awaiting an authoritative bank match.',
        'idempotency_key' => 'cockpit-funding-request-forged-1001',
        'approved_value_minor' => 2_000_000,
        'provider_transaction_id' => 'forged',
    ])->assertSessionHasErrors(['approved_value_minor', 'provider_transaction_id']);

    expect(FundingRequest::query()->count())->toBe(1);
});

it('keeps the review queue fail closed and scoped to configured reviewers', function () {
    $requester = actingAsTestUser();
    $request = app(CreateFundingRequest::class)->handle(new CreateFundingRequestData(
        accountReference: 'wallet:'.$requester->wallet->uuid,
        requesterType: $requester::class,
        requesterId: (string) $requester->getKey(),
        fundingType: FundingRequestType::CashHandover,
        requestedValueMinor: 100_000,
        currency: 'PHP',
        description: 'Cash custody request requiring independent review.',
        idempotencyKey: 'cockpit-review-queue-1001',
    ));
    $unrelated = actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonCount(0, 'props.funding_requests.review_queue')
        ->assertJsonPath('props.funding_requests.controls.reviewer', false);

    $this->post(route(
        'x-change.cockpit.funding.requests.reviews.store',
        $request,
    ), [
        'recognized_value_minor' => 100_000,
        'currency' => 'PHP',
        'connection_reference' => 'netbank-primary',
        'evidence_reference' => 'custody:1001',
    ])->assertForbidden();

    config()->set('x-change.funding.requests.reviewer_ids', [
        (string) $unrelated->getKey(),
    ]);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonCount(1, 'props.funding_requests.review_queue')
        ->assertJsonPath(
            'props.funding_requests.review_queue.0.reference',
            $request->reference,
        )
        ->assertJsonPath('props.funding_requests.controls.reviewer', true)
        ->assertJsonMissingPath(
            'props.funding_requests.review_queue.0.account_reference',
        );
});

it('binds Account Funding Code claims to the intended Account owner', function () {
    $requester = actingAsTestUser();
    $other = User::query()->create([
        'name' => 'Other Funding User',
        'email' => 'other-funding-user@example.test',
        'password' => 'password',
    ]);
    fundTestUserWallet($other, 0);
    $request = FundingRequest::query()->create([
        'account_reference' => 'wallet:'.$requester->wallet->uuid,
        'requester_type' => $requester::class,
        'requester_id' => (string) $requester->getKey(),
        'funding_type' => FundingRequestType::BankTransfer,
        'requested_value_minor' => 1_000,
        'approved_value_minor' => 1_000,
        'currency' => 'PHP',
        'status' => FundingRequestStatus::CodeIssued,
        'version' => 3,
        'idempotency_key_hash' => hash('sha256', 'claim-owner-test'),
        'idempotency_fingerprint' => hash('sha256', 'claim-owner-fingerprint'),
        'description' => 'Owner binding test request.',
        'submitted_at' => now(),
    ]);
    $code = $request->fundingCode()->create([
        'code_hash' => hash('sha256', 'OWNERBOUND01'),
        'code_ciphertext' => 'OWNERBOUND01',
        'code_last_four' => 'ND01',
        'recipient_type' => $requester::class,
        'recipient_id' => (string) $requester->getKey(),
        'account_reference' => $request->account_reference,
        'amount_minor' => 1_000,
        'currency' => 'PHP',
        'connection_reference' => 'netbank-primary',
        'source_position_reference' => 'position:source',
        'reserve_position_reference' => 'position:reserve',
        'destination_position_reference' => 'position:destination',
        'reservation_operation_reference' => 'reservation:owner-binding',
        'claim_operation_reference' => 'claim:owner-binding',
        'status' => 'issued',
        'version' => 1,
        'issued_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($other)
        ->post(route('x-change.cockpit.funding.codes.claims.store', $code))
        ->assertForbidden();

    expect($code->refresh()->status->value)->toBe('issued');
});
