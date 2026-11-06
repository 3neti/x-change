<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentGate;

it('allows an owner to invite optional contributions without altering payment requirements', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 3).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'voucher_type' => 'payable', 'target_amount' => 100,
        'metadata' => ['flow_type' => 'collectible', 'collection_wallet_id' => $owner->wallet->id],
    ]));
    $envelope = new Envelope(['reference_code' => $voucher->code, 'driver_id' => 'simple.test', 'driver_version' => '1.0.0', 'status' => 'draft']);
    $envelope->reference()->associate($voucher);
    $envelope->save();
    $before = data_get($voucher->metadata, 'instructions');

    $this->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])
        ->assertSuccessful()->assertJsonFragment(['TEST_DOC']);
    $invitation = $this->postJson(route('x-change.cockpit.contribution-invitations.store', ['code' => $voucher->code]), [
        'document_types' => ['TEST_DOC'], 'expires_days' => 7,
    ])->assertCreated()->json('token');
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $this->postJson(route('x-change.contributions.documents.store'), [
        'token' => $invitation,
        'document_type' => 'TEST_DOC',
        'file' => UploadedFile::fake()->createWithContent('support.pdf', "%PDF-1.4\nexample\n%%EOF"),
    ])->assertCreated();

    expect(data_get($voucher->fresh()->metadata, 'instructions'))->toBe($before);
    app(DocumentPaymentGate::class)->ensureReady($voucher->fresh());
});

it('allows only the voucher owner to create and revoke scoped invitations', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 3).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => ['flow_type' => 'collectible', 'collection_wallet_id' => $owner->wallet->id],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => [[
            'type' => 'TEST_DOC', 'timing' => 'before_payment', 'requires_acceptance' => true, 'contributor' => 'invited',
        ]]],
    ]));
    $envelope = new Envelope([
        'reference_code' => $voucher->code, 'driver_id' => 'simple.test', 'driver_version' => '1.0.0', 'status' => 'draft',
    ]);
    $envelope->reference()->associate($voucher);
    $envelope->save();
    $url = route('x-change.cockpit.contribution-invitations.store', ['code' => $voucher->code]);
    $input = ['document_types' => ['TEST_DOC'], 'expires_days' => 1, 'password' => 'secret-password'];
    expect(fn () => app(DocumentPaymentGate::class)->ensureReady($voucher->fresh()))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);

    $other = actingAsTestUser();
    $this->postJson($url, $input)->assertNotFound();
    expect(EnvelopeContributionToken::query()->count())->toBe(0);

    $this->actingAs($owner);
    $response = $this->postJson($url, $input)->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
    $token = EnvelopeContributionToken::query()->sole();
    expect($response->json('token'))->toBe($token->token)
        ->and($token->verifyPassword('secret-password'))->toBeTrue()
        ->and(data_get($token->metadata, 'scope.document_types'))->toBe(['TEST_DOC']);

    $context = route('x-change.contributions.context');
    $this->postJson($context, ['token' => $token->token])->assertSuccessful()
        ->assertJsonPath('verified', false)->assertJsonPath('document_types', []);

    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $upload = route('x-change.contributions.documents.store');
    $file = fn () => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\nexample\n%%EOF");
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->postJson(route('x-change.contributions.password.verify'), ['token' => $token->token, 'password' => 'secret-password'])->assertNoContent();
    $this->postJson($context, ['token' => $token->token])->assertSuccessful()
        ->assertJsonPath('verified', true)->assertJsonPath('document_types.0', 'TEST_DOC');
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'WRONG', 'file' => $file()])->assertForbidden();
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => UploadedFile::fake()->createWithContent('document.txt', 'not a document')])->assertUnprocessable();
    $uploaded = $this->postJson($upload, [
        'token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => $file(), 'contributor_role' => 'payer',
    ])->assertCreated();
    $attachment = $envelope->attachments()->findOrFail($uploaded->json('id'));
    expect(data_get($attachment->metadata, 'contributor_role'))->toBe('invited')
        ->and($attachment->disk)->toBe('local');
    $this->postJson($context, ['token' => $token->token])->assertSuccessful()
        ->assertJsonCount(1, 'documents')->assertJsonPath('documents.0.id', $attachment->id);
    $separate = $this->postJson($url, ['document_types' => ['TEST_DOC'], 'expires_days' => 1])->assertCreated();
    $this->postJson($context, ['token' => $separate->json('token')])->assertSuccessful()->assertJsonCount(0, 'documents');
    $this->get(route('x-change.contributions.show'), ['X-Inertia' => 'true'])->assertSuccessful()
        ->assertHeader('Referrer-Policy', 'no-referrer');
    $this->post(route('x-change.contributions.documents.show', ['attachment' => $attachment->id]), ['token' => $token->token])->assertSuccessful();
    $review = route('x-change.cockpit.documents.review', ['code' => $voucher->code, 'attachment' => $attachment->id]);
    $this->actingAs($owner)->get(route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])
        ->assertSuccessful()->assertJsonPath('props.read_model.voucher.settlement.envelope.attachments.0.review_href', $review)
        ->assertJsonPath('props.read_model.voucher.settlement.envelope.contribution.types.0', 'TEST_DOC')
        ->assertJsonPath('props.read_model.voucher.settlement.envelope.contribution.invitations.0.id', $separate->json('id'));
    $this->actingAs($other)->postJson($review, ['decision' => 'accepted'])->assertNotFound();
    $this->actingAs($owner)->postJson($review, ['decision' => 'rejected'])->assertUnprocessable();
    $this->postJson($review, ['decision' => 'rejected', 'reason' => 'Please resubmit'])->assertNoContent();
    expect($attachment->fresh()->review_status)->toBe('rejected');
    $this->postJson($context, ['token' => $token->token])->assertSuccessful()
        ->assertJsonPath('documents.0.rejection_reason', 'Please resubmit');
    $this->postJson($context, ['token' => $separate->json('token')])->assertSuccessful()->assertJsonCount(0, 'documents');
    expect(fn () => app(DocumentPaymentGate::class)->ensureReady($voucher->fresh()))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    $this->postJson($review, ['decision' => 'accepted'])->assertStatus(409);
    $replacement = $this->postJson($upload, ['token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => $file()])->assertCreated();
    $replacementReview = route('x-change.cockpit.documents.review', ['code' => $voucher->code, 'attachment' => $replacement->json('id')]);
    $this->postJson($replacementReview, ['decision' => 'accepted'])->assertNoContent();
    app(DocumentPaymentGate::class)->ensureReady($voucher->fresh());

    $revoke = route('x-change.cockpit.contribution-invitations.destroy', ['code' => $voucher->code, 'invitation' => $token->id]);
    $this->actingAs($other)->deleteJson($revoke)->assertNotFound();
    $this->actingAs($owner)->deleteJson($revoke)->assertNoContent();
    expect($token->fresh()->isRevoked())->toBeTrue();
    $this->postJson($context, ['token' => $token->token])->assertNotFound();
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->deleteJson($revoke)->assertNoContent();
});
