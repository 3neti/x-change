<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\Envelope;

it('requires a voucher and role scoped browser session for payer uploads', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 3).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: [
        'voucher_type' => 'payable', 'target_amount' => 100,
        'metadata' => ['flow_type' => 'collectible', 'collection_wallet_id' => $issuer->wallet->id],
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => [[
            'type' => 'TEST_DOC', 'timing' => 'before_payment', 'requires_acceptance' => true, 'contributor' => 'payer',
        ]]],
    ]));
    $envelope = new Envelope(['reference_code' => $voucher->code, 'driver_id' => 'simple.test', 'driver_version' => '1.0.0', 'status' => 'draft']);
    $envelope->reference()->associate($voucher);
    $envelope->save();
    $uploadUrl = route('x-change.pay.documents.store', ['code' => $voucher->code]);
    $file = fn () => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\nexample\n%%EOF");

    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful();
    $this->postJson($uploadUrl, ['document_type' => 'OTHER', 'file' => $file()])->assertNotFound();
    $response = $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file(), 'contributor_role' => 'claimant'])->assertCreated();
    $attachment = $envelope->attachments()->findOrFail($response->json('id'));
    expect(data_get($attachment->metadata, 'contributor_role'))->toBe('payer')
        ->and($attachment->disk)->toBe('local');
    $this->postJson(route('x-change.claim.documents.store', ['code' => $voucher->code]), ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    config()->set('filesystems.disks.local.visibility', 'public');
    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertStatus(503);
    config()->set('filesystems.disks.local.visibility', 'private');
    $this->travel(31)->minutes();
    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful();
    session()->flush();
    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
});

it('grants claimant document access only after the claim page is validated', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 3).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => [[
            'type' => 'TEST_DOC', 'timing' => 'later_review', 'requires_acceptance' => true, 'contributor' => 'claimant',
        ]]],
    ]));
    $envelope = new Envelope(['reference_code' => $voucher->code, 'driver_id' => 'simple.test', 'driver_version' => '1.0.0', 'status' => 'draft']);
    $envelope->reference()->associate($voucher);
    $envelope->save();
    auth()->logout();
    $uploadUrl = route('x-change.claim.documents.store', ['code' => $voucher->code]);
    $file = fn () => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\nexample\n%%EOF");

    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->get(route('x-change.claim.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful();
    $result = $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertCreated();
    expect(data_get($envelope->attachments()->findOrFail($result->json('id'))->metadata, 'contributor_role'))->toBe('claimant');
});
