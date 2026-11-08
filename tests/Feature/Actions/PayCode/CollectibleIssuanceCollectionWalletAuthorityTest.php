<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentGate;
use LBHurtado\XChange\Services\Settlement\IssuerInvoiceDrafts;
use LBHurtado\XChange\Tests\Fakes\User;

/**
 * @return array<string, mixed>
 */
function collectionWalletAuthorityPayload(
    ?string $voucherType,
    ?string $flowType,
    ?string $callerWalletId,
): array {
    $metadata = [];

    if ($flowType !== null) {
        $metadata['flow_type'] = $flowType;
    }

    if ($callerWalletId !== null) {
        $metadata['collection_wallet_id'] = $callerWalletId;
    }

    return [
        'cash' => [
            'amount' => 0,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'AUTH',
        'mask' => '****',
        'voucher_type' => $voucherType,
        'target_amount' => 100,
        'metadata' => $metadata,
    ];
}

function collectionWalletAuthorityUser(string $name): User
{
    $user = User::query()->create([
        'name' => $name,
        'email' => Str::uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    fundTestUserWallet($user);

    return $user;
}

function frozenCollectionWalletId(array $result): string
{
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);

    return (string) data_get(
        $voucher->metadata,
        'instructions.metadata.collection_wallet_id',
    );
}

it('creates a nonblocking default envelope for newly issued collectible vouchers', function (string $type, string $flow, bool $v2): void {
    $issuer = actingAsTestUser();
    $payload = collectionWalletAuthorityPayload($type, $flow, null);
    if ($v2) {
        $payload['execution'] = ['schema' => 'voucher.execution.v2', 'driver' => 'default'];
        if ($type === 'settlement') {
            $payload['execution']['metadata'] = ['settlement_workflow' => 'collect_for_settlement'];
        }
    }
    $result = app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);

    expect($voucher->envelopes()->count())->toBe(1)
        ->and($voucher->envelope()->firstOrFail()->driver_id)->toBe('x-change.documents')
        ->and(data_get($voucher->metadata, 'instructions.execution.document_requirements'))->toBeNull();
    app(DocumentPaymentGate::class)->ensureReady($voucher);
})->with([
    ['payable', 'collectible', false], ['settlement', 'settlement', false],
    ['payable', 'collectible', true], ['settlement', 'settlement', true],
]);

it('attaches an envelope to every voucher generated in a batch', function (): void {
    $issuer = actingAsTestUser();
    $payload = collectionWalletAuthorityPayload('payable', 'collectible', null);
    $payload['count'] = 2;
    app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $vouchers = Voucher::query()->where('owner_type', $issuer->getMorphClass())->where('owner_id', $issuer->getKey())->get();

    expect($vouchers)->toHaveCount(2);
    foreach ($vouchers as $voucher) {
        expect($voucher->envelopes()->count())->toBe(1);
    }
});

it('creates a document envelope for a disbursable Pay Code requesting claimant documents', function (): void {
    $issuer = actingAsTestUser();
    $payload = collectionWalletAuthorityPayload(null, null, null);
    $payload['cash']['amount'] = 100;
    unset($payload['target_amount']);
    $payload['inputs'] = ['fields' => ['documents'], 'documents' => [[
        'key' => 'id', 'label' => 'Identity', 'accept' => ['image/jpeg'], 'required' => true,
    ]]];
    $result = app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);

    expect($voucher->envelopes()->count())->toBe(1)
        ->and($voucher->envelope()->firstOrFail()->driver_id)->toBe('x-change.documents')
        ->and(data_get($voucher->metadata, 'instructions.inputs.documents.0.key'))->toBe('id');
});

it('issues v2 payment documents with stable identity and separate audit references', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $issuer = actingAsTestUser();
    $drafts = app(IssuerInvoiceDrafts::class);
    $references = [];
    $documents = [];
    foreach (['invoice.pdf', 'receipt.pdf'] as $filename) {
        $reference = $drafts->create($issuer, UploadedFile::fake()->createWithContent($filename, "%PDF-1.4\nexample\n%%EOF"));
        $drafts->bind($issuer, $reference, 'document-order');
        $references[] = $reference;
        $documents[] = ['id' => $drafts->documentId($issuer, $reference), 'type' => 'invoice', 'label' => $filename];
    }
    $payload = collectionWalletAuthorityPayload('payable', 'collectible', null);
    $payload['execution'] = ['schema' => 'voucher.execution.v2', 'driver' => 'default'];
    $payload['payment_details'] = ['description' => 'Computer repair', 'reference' => 'INV-1', 'documents' => $documents];
    $payload['issuer_invoice_drafts'] = $references;
    $payload['_meta'] = ['idempotency_key' => 'document-order'];
    $result = app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);
    $attachments = $voucher->envelope()->firstOrFail()->attachments()->orderBy('id')->get();
    expect($attachments->pluck('document_id')->all())->toBe(array_column($documents, 'id'))
        ->and($attachments->pluck('audit_reference')->all())->toBe([$voucher->code.'-DOC-001', $voucher->code.'-DOC-002'])
        ->and(data_get($voucher->metadata, 'instructions.payment_details.documents'))->toBe($documents)
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.rider_stamp_artifact'))->toBeNull()
        ->and(data_get($voucher->metadata, 'rider_stamp_artifact'))->not->toBeNull()
        ->and(app(RiderStampArtifactStoreContract::class)->read($voucher))->not->toBeNull();
    auth()->logout();
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])
        ->assertSuccessful()->assertJsonPath('props.payment.payment_details.description', 'Computer repair')
        ->assertJsonPath('props.payment.payment_details.reference', 'INV-1')
        ->assertJsonCount(2, 'props.payment.shared_invoices');
});

it('issues a document from a private object-style disk and detects altered drafts', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('invoice-objects');
    config()->set('filesystems.disks.invoice-objects.driver', 's3');
    config()->set('filesystems.disks.invoice-objects.visibility', 'private');
    config()->set('settlement-envelope.contribution_storage_disk', 'invoice-objects');
    $issuer = actingAsTestUser();
    $drafts = app(IssuerInvoiceDrafts::class);
    $reference = $drafts->create($issuer, UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\nexample\n%%EOF"));
    $drafts->assertAllowed($issuer, $reference, false);
    $drafts->bind($issuer, $reference, 'object-order');
    $payload = collectionWalletAuthorityPayload('payable', 'collectible', null);
    $payload['issuer_invoice_draft'] = $reference;
    $payload['_meta'] = ['idempotency_key' => 'object-order'];
    $result = app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $attachment = Voucher::query()->findOrFail($result['voucher_id'])->envelope()->firstOrFail()->attachments()->sole();
    expect($attachment->disk)->toBe('invoice-objects');
    Storage::disk('invoice-objects')->assertExists($attachment->file_path);

    $altered = $drafts->create($issuer, UploadedFile::fake()->createWithContent('other.pdf', "%PDF-1.4\noriginal\n%%EOF"));
    $path = DB::table('x_change_issuer_invoice_drafts')->where('reference', $altered)->value('path');
    Storage::disk('invoice-objects')->put($path, 'changed');
    expect(fn () => $drafts->assertAllowed($issuer, $altered, false))->toThrow(ValidationException::class);
});

it('rolls back attachments if an issuance references the wrong staged document', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $issuer = actingAsTestUser();
    $drafts = app(IssuerInvoiceDrafts::class);
    $references = [];
    $documents = [];
    foreach (['invoice.pdf', 'receipt.pdf'] as $filename) {
        $reference = $drafts->create($issuer, UploadedFile::fake()->createWithContent($filename, "%PDF-1.4\nexample\n%%EOF"));
        $drafts->bind($issuer, $reference, 'document-order');
        $references[] = $reference;
        $documents[] = ['id' => $drafts->documentId($issuer, $reference), 'type' => 'invoice', 'label' => $filename];
    }
    $documents[1]['id'] = (string) Str::ulid();
    $payload = collectionWalletAuthorityPayload('payable', 'collectible', null);
    $payload['execution'] = ['schema' => 'voucher.execution.v2', 'driver' => 'default'];
    $payload['payment_details'] = ['documents' => $documents];
    $payload['issuer_invoice_drafts'] = $references;
    $payload['_meta'] = ['idempotency_key' => 'document-order'];
    expect(fn () => app(PayCodeIssuanceContract::class)->issue($issuer, $payload))->toThrow(ValidationException::class);
    expect(Storage::disk('local')->allFiles('envelopes'))->toBe([]);
});

it('issues a payable with the privately staged invoice from its bound order', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $issuer = actingAsTestUser();
    $drafts = app(IssuerInvoiceDrafts::class);
    $reference = $drafts->create($issuer, UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\nexample\n%%EOF"));
    $drafts->bind($issuer, $reference, 'invoice-order');
    $payload = collectionWalletAuthorityPayload('payable', 'collectible', null);
    $payload['issuer_invoice_draft'] = $reference;
    $payload['_meta'] = ['idempotency_key' => 'invoice-order'];
    $result = app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);
    $attachment = $voucher->envelope()->firstOrFail()->attachments()->sole();
    expect(data_get($attachment->metadata, 'shared_with_payer'))->toBeTrue();
    $other = issueVoucher(validVoucherInstructions());
    auth()->logout();
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])
        ->assertSuccessful()->assertJsonPath('props.payment.shared_invoices.0.name', 'invoice.pdf');
    $this->get(route('x-change.pay.invoice.show', ['code' => $voucher->code, 'attachment' => $attachment->id]))->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=invoice.pdf');
    $attachment->forceFill(['mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->save();
    $this->get(route('x-change.pay.invoice.show', ['code' => $voucher->code, 'attachment' => $attachment->id]))
        ->assertSuccessful()->assertHeader('Content-Disposition', 'attachment; filename=invoice.pdf');
    $this->get(route('x-change.pay.invoice.show', ['code' => $other->code, 'attachment' => $attachment->id]))->assertNotFound();
});

it('creates a document envelope atomically with a payable Pay Code', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 4).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    $issuer = actingAsTestUser();
    $payload = collectionWalletAuthorityPayload('payable', 'collectible', null);
    $payload['execution'] = ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => [[
        'type' => 'SUPPORTING_DOCUMENT', 'timing' => 'before_payment', 'requires_acceptance' => true, 'contributor' => 'payer',
    ], [
        'type' => 'INVOICE', 'timing' => 'before_payment', 'requires_acceptance' => false, 'contributor' => 'payer',
    ]]];

    $result = app(PayCodeIssuanceContract::class)->issue($issuer, $payload);
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);
    expect($voucher->envelope()->firstOrFail()->driver_id)->toBe('x-change.documents');
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful();
    $upload = $this->postJson(route('x-change.pay.documents.store', ['code' => $voucher->code]), [
        'document_type' => 'SUPPORTING_DOCUMENT',
        'file' => UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\nexample\n%%EOF"),
    ])->assertCreated();
    $this->postJson(route('x-change.cockpit.documents.review', ['code' => $voucher->code, 'attachment' => $upload->json('id')]), ['decision' => 'accepted'])->assertNoContent();
    expect(fn () => app(DocumentPaymentGate::class)->ensureReady($voucher->fresh()))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
    $this->postJson(route('x-change.pay.documents.store', ['code' => $voucher->code]), [
        'document_type' => 'INVOICE',
        'file' => UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\nexample\n%%EOF"),
    ])->assertCreated();
    app(DocumentPaymentGate::class)->ensureReady($voucher->fresh());

    $before = Voucher::query()->count();
    $payload['execution']['document_requirements'][0]['type'] = 'UNSUPPORTED';
    expect(fn () => app(PayCodeIssuanceContract::class)->issue($issuer, $payload))
        ->toThrow(ValidationException::class);
    expect(Voucher::query()->count())->toBe($before);
});

it('overwrites a foreign caller wallet for payable and settlement issuance', function (string $voucherType): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $foreign = collectionWalletAuthorityUser('Foreign wallet owner');
    $foreignWallet = $foreign->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: $voucherType,
            flowType: 'collectible',
            callerWalletId: (string) $foreignWallet->getKey(),
        ),
    );

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey())
        ->not->toBe((string) $foreignWallet->getKey());
})->with(['payable', 'settlement']);

it('overrides a foreign collection wallet for payable issuance without flow type', function (): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $foreign = collectionWalletAuthorityUser('Foreign wallet owner');
    $foreignWallet = $foreign->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: 'payable',
            flowType: null,
            callerWalletId: (string) $foreignWallet->getKey(),
        ),
    );
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey())
        ->and(data_get($voucher->metadata, 'instructions.metadata.issuer_id'))
        ->toBe((string) $issuer->getAuthIdentifier());
});

it('applies issuer wallet authority to an explicit collectible flow', function (): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();
    $foreign = collectionWalletAuthorityUser('Foreign wallet owner');
    $foreignWallet = $foreign->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: null,
            flowType: 'collectible',
            callerWalletId: (string) $foreignWallet->getKey(),
        ),
    );

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey());
});

it('derives the collection wallet when the caller honestly omits it', function (): void {
    $issuer = actingAsTestUser();
    $issuerWallet = $issuer->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue(
        $issuer,
        collectionWalletAuthorityPayload(
            voucherType: 'payable',
            flowType: 'collectible',
            callerWalletId: null,
        ),
    );

    expect(frozenCollectionWalletId($result))
        ->toBe((string) $issuerWallet->getKey());
});
