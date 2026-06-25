<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LBHurtado\Voucher\Contracts\GeneratesVouchers;
use LBHurtado\Voucher\Contracts\RedeemsVouchers;

it('resolves voucher runtime contracts from the package container', function () {
    expect(app(GeneratesVouchers::class))->toBeInstanceOf(GeneratesVouchers::class)
        ->and(app(RedeemsVouchers::class))->toBeInstanceOf(RedeemsVouchers::class);
});

it('keeps x-change production code behind voucher runtime contracts', function () {
    $packageRoot = dirname(__DIR__, 3);

    $blockedImports = [
        'use LBHurtado\\Voucher\\Actions\\GenerateVouchers;',
        'use LBHurtado\\Voucher\\Actions\\RedeemVoucher;',
    ];

    $violations = collect(File::allFiles($packageRoot.'/src'))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.php'))
        ->flatMap(fn (SplFileInfo $file): array => collect($blockedImports)
            ->filter(fn (string $blockedImport): bool => str_contains($file->getContents(), $blockedImport))
            ->map(fn (string $blockedImport): string => str_replace($packageRoot.'/', '', $file->getPathname()).' imports '.$blockedImport)
            ->all())
        ->values()
        ->all();

    expect($violations)->toBe([]);
});
