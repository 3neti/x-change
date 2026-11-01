<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentGate;

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

    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $upload = route('x-change.contributions.documents.store');
    $file = fn () => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\nexample\n%%EOF");
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->postJson(route('x-change.contributions.password.verify'), ['token' => $token->token, 'password' => 'secret-password'])->assertNoContent();
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'WRONG', 'file' => $file()])->assertForbidden();
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => UploadedFile::fake()->createWithContent('document.txt', 'not a document')])->assertUnprocessable();
    $uploaded = $this->postJson($upload, [
        'token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => $file(), 'contributor_role' => 'payer',
    ])->assertCreated();
    $attachment = $envelope->attachments()->findOrFail($uploaded->json('id'));
    expect(data_get($attachment->metadata, 'contributor_role'))->toBe('invited')
        ->and($attachment->disk)->toBe('local');
    $this->post(route('x-change.contributions.documents.show', ['attachment' => $attachment->id]), ['token' => $token->token])->assertSuccessful();
    $review = route('x-change.cockpit.documents.review', ['code' => $voucher->code, 'attachment' => $attachment->id]);
    $this->actingAs($other)->postJson($review, ['decision' => 'accepted'])->assertNotFound();
    $this->actingAs($owner)->postJson($review, ['decision' => 'rejected'])->assertUnprocessable();
    $this->postJson($review, ['decision' => 'rejected', 'reason' => 'Please resubmit'])->assertNoContent();
    expect($attachment->fresh()->review_status)->toBe('rejected');
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
    $this->postJson($upload, ['token' => $token->token, 'document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->deleteJson($revoke)->assertNoContent();
});
