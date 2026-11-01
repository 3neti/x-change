<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;

it('only serves a private intact document to the invitation that submitted it', function (): void {
    Storage::fake('local');
    config()->set('filesystems.disks.local.visibility', 'private');
    $envelope = Envelope::query()->create([
        'reference_code' => 'DOCUMENT-TEST', 'driver_id' => 'test', 'driver_version' => '1.0.0', 'status' => 'draft',
    ]);
    $attributes = [
        'envelope_id' => $envelope->id,
        'expires_at' => now()->addHour(),
        'metadata' => ['scope' => ['actions' => ['read_own'], 'document_types' => ['INVOICE']]],
    ];
    $owner = EnvelopeContributionToken::query()->create($attributes);
    $other = EnvelopeContributionToken::query()->create($attributes);
    Storage::disk('local')->put('documents/test.pdf', 'document contents');
    $document = EnvelopeAttachment::query()->create([
        'envelope_id' => $envelope->id, 'doc_type' => 'INVOICE', 'original_filename' => 'test.pdf',
        'file_path' => 'documents/test.pdf', 'disk' => 'local', 'mime_type' => 'application/pdf', 'size' => 17,
        'hash' => hash('sha256', 'document contents'), 'review_status' => 'pending',
        'metadata' => ['contributor_role' => 'invited', 'contribution_token_id' => $owner->id],
    ]);
    $url = route('x-change.contributions.documents.show', ['attachment' => $document->id]);

    $this->post($url, ['token' => $other->token])->assertNotFound();
    $this->post($url, ['token' => $owner->token])->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('document contents');
    Storage::disk('local')->put('documents/test.pdf', 'changed');
    $this->post($url, ['token' => $owner->token])->assertNotFound();
    Storage::disk('local')->put('documents/test.pdf', 'document contents');
    config()->set('filesystems.disks.local.visibility', 'public');
    $this->post($url, ['token' => $owner->token])->assertNotFound();
    config()->set('filesystems.disks.local.visibility', 'private');
    $owner->update(['revoked_at' => now()]);
    $this->post($url, ['token' => $owner->token])->assertNotFound();
});
