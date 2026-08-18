<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Actions\Keepsake\PlanInstanceKeepsakeExport;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;

it('exports durable evidence once and excludes it from the inert blueprint', function () {
    Storage::fake('claim-evidence');
    $user = actingAsTestUser();
    $voucher = issueVoucher();
    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'full',
        'settlement_mode' => 'provider',
        'status' => 'succeeded',
        'currency' => 'PHP',
        'reference' => 'claim-keepsake-test',
        'idempotency_key' => 'claim-keepsake-test',
    ]);
    $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=', true);
    Storage::disk('claim-evidence')->put('selfie.png', $contents);
    $evidence = VoucherClaimEvidence::query()->create([
        'voucher_claim_id' => $claim->getKey(),
        'voucher_id' => $voucher->getKey(),
        'requirement_key' => 'selfie',
        'kind' => 'image',
        'status' => 'verified',
        'summary' => 'Selfie retained',
        'payload' => ['artifact' => 'stored'],
        'artifact_disk' => 'claim-evidence',
        'artifact_path' => 'selfie.png',
        'mime_type' => 'image/png',
        'size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'captured_at' => now(),
        'verified_at' => now(),
        'metadata' => [],
    ]);
    $voucher->inputs()->create([
        'name' => 'selfie',
        'value' => json_encode([
            'claim_evidence_id' => $evidence->getKey(),
            'stored' => true,
        ], JSON_THROW_ON_ERROR),
    ]);

    $plan = app(PlanInstanceKeepsakeExport::class)->handle(
        allUsers: true,
        userIdentifiers: [],
        includes: ['claim-evidence', 'blueprint'],
        includePersonalData: false,
        includeLocationData: false,
        allowIncomplete: false,
        materializeArtifacts: true,
    );
    $contribution = collect($plan->contributions)->firstWhere('key', 'claim-evidence');

    expect($plan->artifactCount)->toBe(1)
        ->and($contribution->artifacts)->toHaveCount(1)
        ->and($contribution->artifacts[0]['contents'])->toBe($contents)
        ->and($contribution->artifacts[0]['source'])->toBe('durable')
        ->and($contribution->blueprintFiles)->toBe([])
        ->and(implode('', $contribution->snapshotFiles))->not->toContain(base64_encode($contents));
});
