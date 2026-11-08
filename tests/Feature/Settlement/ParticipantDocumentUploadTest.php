<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\XChange\Services\Settlement\IssuerInvoiceDrafts;

it('links an existing private document into an envelope without copying it', function (): void {
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    Storage::disk('local')->put('uploads/evidence.pdf', '%PDF evidence', 'private');
    $voucher = issueVoucher(validVoucherInstructions());
    $service = app(EnvelopeService::class);
    $envelope = $service->create($voucher->code, 'x-change.documents', '1.0.0', $voucher);
    $document = [
        'document_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'doc_type' => 'SUPPORTING_DOCUMENT',
        'original_filename' => 'evidence.pdf', 'disk' => 'local', 'file_path' => 'uploads/evidence.pdf',
        'mime_type' => 'application/pdf', 'size' => strlen('%PDF evidence'), 'hash' => hash('sha256', '%PDF evidence'),
    ];
    $first = $service->attachStoredAttachment($envelope, $document);
    $retry = $service->attachStoredAttachment($envelope, $document);
    expect($retry->id)->toBe($first->id)
        ->and($envelope->attachments()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe(['uploads/evidence.pdf']);
    Storage::disk('local')->put('uploads/evidence.pdf', 'altered data');
    expect(fn () => $service->attachStoredAttachment($envelope, $document))->toThrow(InvalidArgumentException::class);
});

it('keeps issuer invoice drafts private and bound to one issuer and order', function (): void {
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $owner = actingAsTestUser();
    $file = fn () => UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\nexample\n%%EOF");
    $response = $this->postJson(route('x-change.cockpit.quick-generate.invoice-drafts.store'), ['invoice' => $file()])->assertCreated();
    $reference = $response->json('reference');
    $draft = DB::table('x_change_issuer_invoice_drafts')->where('reference', $reference)->first();
    expect($response->json('document_id'))->toBe($draft->document_id)->toHaveLength(26);
    Storage::disk('local')->assertExists($draft->path);
    $drafts = app(IssuerInvoiceDrafts::class);
    $drafts->bind($owner, $reference, 'order-1');
    $drafts->bind($owner, $reference, 'order-1');
    expect(fn () => $drafts->bind($owner, $reference, 'other-order'))->toThrow(ValidationException::class);
    $other = actingAsTestUser();
    expect(fn () => $drafts->bind($other, $reference, 'order-1'))->toThrow(ValidationException::class);
    $unbound = $drafts->create($owner, $file());
    $this->travel(8)->days();
    expect(fn () => $drafts->bind($owner, $unbound, 'order-2'))->toThrow(ValidationException::class);
    $drafts->bind($owner, $reference, 'order-1');
});

it('validates issuer document content and configured size', function (): void {
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    config()->set('x-change.documents.issuer_max_kilobytes', 10);
    actingAsTestUser();
    $url = route('x-change.cockpit.quick-generate.invoice-drafts.store');
    $this->postJson($url, ['invoice' => UploadedFile::fake()->createWithContent('report.csv', "item,amount\nrepair,100\n")])->assertCreated();
    $this->postJson($url, ['invoice' => UploadedFile::fake()->createWithContent('report.csv', "%PDF-1.4\nexample\n%%EOF")])->assertUnprocessable();
    $this->postJson($url, ['invoice' => UploadedFile::fake()->create('large.pdf', 11, 'application/pdf')])->assertUnprocessable();
    $this->postJson($url, ['invoice' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')])->assertUnprocessable();
});

it('accepts Office Open XML documents using their detected content types', function (string $extension, string $entry): void {
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    actingAsTestUser();
    $path = tempnam(sys_get_temp_dir(), 'invoice-test-');
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
    $zip->addFromString($entry, '<?xml version="1.0"?><data/>');
    $zip->close();
    try {
        $file = new UploadedFile($path, 'invoice.'.$extension, null, null, true);
        $this->post(route('x-change.cockpit.quick-generate.invoice-drafts.store'), ['invoice' => $file], ['Accept' => 'application/json'])->assertCreated();
    } finally {
        @unlink($path);
    }
})->with([
    'Word' => ['docx', 'word/document.xml'],
    'Excel' => ['xlsx', 'xl/workbook.xml'],
]);

it('attaches an issuer invoice once to its bound voucher', function (): void {
    config()->set('settlement-envelope.driver_disk', 'test-envelope-drivers');
    config()->set('filesystems.disks.test-envelope-drivers', [
        'driver' => 'local', 'root' => dirname(__DIR__, 3).'/vendor/3neti/settlement-envelope/resources/stubs/drivers',
    ]);
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $owner = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions());
    $envelope = app(EnvelopeService::class)->create($voucher->code, 'x-change.documents', '1.0.0', $voucher);
    $drafts = app(IssuerInvoiceDrafts::class);
    $reference = $drafts->create($owner, UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\nexample\n%%EOF"));
    $drafts->bind($owner, $reference, 'order-1');
    DB::transaction(function () use ($drafts, $owner, $voucher, $reference): void {
        $drafts->attach($owner, $voucher, $reference, 'order-1', 'INVOICE');
        $drafts->attach($owner, $voucher, $reference, 'order-1', 'INVOICE');
    });
    $attachment = $envelope->attachments()->sole();
    expect(data_get($attachment->metadata, 'shared_with_payer'))->toBeTrue()
        ->and(data_get($attachment->metadata, 'contributor_role'))->toBe('issuer');
});

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
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful()
        ->assertJsonPath('props.payment.document_requirements.0.type', 'TEST_DOC')
        ->assertJsonPath('props.payment.documents_ready', false)
        ->assertJsonPath('props.payment.can_create_attempt', false);
    $this->postJson($uploadUrl, ['document_type' => 'OTHER', 'file' => $file()])->assertNotFound();
    $response = $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file(), 'contributor_role' => 'claimant'])->assertCreated();
    $attachment = $envelope->attachments()->findOrFail($response->json('id'));
    expect(data_get($attachment->metadata, 'contributor_role'))->toBe('payer')
        ->and($attachment->disk)->toBe('local');
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful()
        ->assertJsonPath('props.payment.document_requirements.0.status', 'pending');
    $this->postJson(route('x-change.claim.documents.store', ['code' => $voucher->code]), ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    config()->set('filesystems.disks.local.visibility', 'public');
    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertStatus(503);
    config()->set('filesystems.disks.local.visibility', 'private');
    $this->travel(31)->minutes();
    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful();
    session()->flush();
    $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertNotFound();
    $this->get(route('x-change.pay.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful()
        ->assertJsonPath('props.payment.document_requirements.0.status', null);
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
    $this->get(route('x-change.claim.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful()
        ->assertJsonPath('props.claim_documents.requirements.0.type', 'TEST_DOC');
    $result = $this->postJson($uploadUrl, ['document_type' => 'TEST_DOC', 'file' => $file()])->assertCreated();
    expect(data_get($envelope->attachments()->findOrFail($result->json('id'))->metadata, 'contributor_role'))->toBe('claimant');
    $this->get(route('x-change.claim.show', ['code' => $voucher->code]), ['X-Inertia' => 'true'])->assertSuccessful()
        ->assertJsonPath('props.claim_documents.requirements.0.status', 'pending');
});
