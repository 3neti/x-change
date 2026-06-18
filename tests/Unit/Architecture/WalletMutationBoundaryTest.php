<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps direct Bavix wallet deposits out of x-change production code', function () {
    $packageRoot = dirname(__DIR__, 3);
    $allowed = [
        'src/Actions/Payment/CollectVoucherFunds.php',
    ];

    $violations = collect(File::allFiles($packageRoot.'/src'))
        ->reject(fn (SplFileInfo $file): bool => str_contains($file->getPathname(), 'Console/Commands/Lifecycle/PrepareLifecycleEnvironmentCommand.php'))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.php'))
        ->filter(fn (SplFileInfo $file): bool => preg_match('/->deposit(?:Float)?\s*\(/', $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file): string => str_replace($packageRoot.'/', '', $file->getPathname()))
        ->reject(fn (string $path): bool => in_array($path, $allowed, true))
        ->values()
        ->all();

    expect($violations)->toBe([]);
});

it('keeps direct Bavix wallet withdrawals inside approved wallet boundaries', function () {
    $allowed = [
        'src/Actions/Redemption/WithdrawPayCode.php',
        'src/Services/WithdrawalLifecycleService.php',
        'src/Services/WalletAccessService.php',
    ];

    $packageRoot = dirname(__DIR__, 3);

    $violations = collect(File::allFiles($packageRoot.'/src'))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.php'))
        ->filter(fn (SplFileInfo $file): bool => preg_match('/->(?:withdraw|forceWithdraw)\s*\(/', $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file): string => str_replace($packageRoot.'/', '', $file->getPathname()))
        ->reject(fn (string $path): bool => in_array($path, $allowed, true))
        ->values()
        ->all();

    expect($violations)->toBe([]);
});
