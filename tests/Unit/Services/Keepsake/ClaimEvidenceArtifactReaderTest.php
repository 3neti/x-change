<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceArtifactReader;

it('verifies durable claim evidence bytes', function () {
    Storage::fake('claim-evidence');
    $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=', true);
    Storage::disk('claim-evidence')->put('selfie.png', $contents);
    $evidence = new VoucherClaimEvidence([
        'artifact_disk' => 'claim-evidence',
        'artifact_path' => 'selfie.png',
        'mime_type' => 'image/png',
        'size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
    ]);

    $artifact = app(ClaimEvidenceArtifactReader::class)->stored($evidence);

    expect($artifact['contents'])->toBe($contents)
        ->and($artifact['mime_type'])->toBe('image/png')
        ->and($artifact['sha256'])->toBe(hash('sha256', $contents));
});

it('rejects a durable artifact with mismatched integrity evidence', function () {
    Storage::fake('claim-evidence');
    Storage::disk('claim-evidence')->put('selfie.png', 'not-an-image');
    $evidence = new VoucherClaimEvidence([
        'artifact_disk' => 'claim-evidence',
        'artifact_path' => 'selfie.png',
        'mime_type' => 'image/png',
        'size' => 12,
        'sha256' => str_repeat('0', 64),
    ]);

    expect(fn () => app(ClaimEvidenceArtifactReader::class)->stored($evidence))
        ->toThrow(InstanceKeepsakeException::class);
});

it('does not mistake a modern evidence pointer for legacy base64', function () {
    expect(app(ClaimEvidenceArtifactReader::class)->isStoredPointer(json_encode([
        'claim_evidence_id' => 42,
        'stored' => true,
    ], JSON_THROW_ON_ERROR)))->toBeTrue();
});
