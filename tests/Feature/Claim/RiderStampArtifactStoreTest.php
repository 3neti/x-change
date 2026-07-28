<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;
use LBHurtado\XChange\Data\Claim\RiderStampArtifactData;
use LBHurtado\XChange\Exceptions\RiderStampArtifactUnavailable;

beforeEach(function (): void {
    Storage::fake('local');
});

it('materializes and verifies an immutable content-addressed Stamp artifact', function (): void {
    $voucher = issueVoucher();
    $artifacts = app(RiderStampArtifactStoreContract::class);
    $claimUrl = 'https://example.test/x/claim/'.$voucher->code;

    $artifact = $artifacts->materialize($voucher, $claimUrl);
    $replayed = $artifacts->materialize($voucher, $claimUrl);
    $card = $artifacts->read($voucher);

    expect($artifact->sha256)->toBe($replayed->sha256)
        ->and($artifact->width)->toBe(RiderStampArtifactData::Width)
        ->and($artifact->height)->toBe(RiderStampArtifactData::Height)
        ->and($artifact->mimeType)->toBe(RiderStampArtifactData::MimeType)
        ->and($artifact->renderingManifestVersion)
        ->toBe(RiderStampArtifactData::ManifestVersion)
        ->and($card)->toBeInstanceOf(ClaimShareCardData::class)
        ->and(hash('sha256', $card?->contents ?? ''))->toBe($artifact->sha256)
        ->and($card?->etag)->toBe('"'.$artifact->sha256.'"');

    Storage::disk('local')->assertExists(
        'x-change/claim/stamp-artifacts/'.$artifact->sha256.'.png',
    );

    $voucher->refresh();

    expect(data_get(
        $voucher,
        'instructions.metadata.custom.rider_stamp_artifact',
    ))->toMatchArray($artifact->toArray());
});

it('refuses Stamp bytes that no longer match their persisted descriptor', function (): void {
    $voucher = issueVoucher();
    $artifacts = app(RiderStampArtifactStoreContract::class);
    $artifact = $artifacts->materialize(
        $voucher,
        'https://example.test/x/claim/'.$voucher->code,
    );

    Storage::disk('local')->put(
        'x-change/claim/stamp-artifacts/'.$artifact->sha256.'.png',
        'tampered',
    );

    expect($artifacts->read($voucher))->toBeNull();
});

it('persists a verified Stamp artifact before Pay Code issuance finishes', function (): void {
    $user = actingAsTestUser();
    $result = app(PayCodeIssuanceContract::class)->issue(
        $user,
        validVoucherInstructions()->toArray(),
    );
    $voucher = Voucher::query()->findOrFail($result['voucher_id']);
    $artifact = app(RiderStampArtifactStoreContract::class)->descriptor($voucher);

    expect($artifact)->toBeInstanceOf(RiderStampArtifactData::class)
        ->and(app(RiderStampArtifactStoreContract::class)->read($voucher))
        ->toBeInstanceOf(ClaimShareCardData::class);
});

it('rolls back issuance when a canonical Stamp artifact cannot be rendered', function (): void {
    $this->app->bind(
        RiderStampClaimCardComposerContract::class,
        fn (): RiderStampClaimCardComposerContract => new class implements RiderStampClaimCardComposerContract
        {
            public function compose(Voucher $voucher, string $claimUrl): ClaimShareCardData
            {
                return new ClaimShareCardData(
                    contents: 'not-a-png',
                    etag: '"invalid"',
                );
            }
        },
    );

    $user = actingAsTestUser();
    $voucherCount = Voucher::query()->count();

    expect(fn () => app(PayCodeIssuanceContract::class)->issue(
        $user,
        validVoucherInstructions()->toArray(),
    ))->toThrow(
        RiderStampArtifactUnavailable::class,
        RiderStampArtifactUnavailable::Message,
    );

    expect(Voucher::query()->count())->toBe($voucherCount);
});
