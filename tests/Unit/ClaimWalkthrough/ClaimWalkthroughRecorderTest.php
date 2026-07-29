<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use LBHurtado\XChange\ClaimWalkthrough\ClaimWalkthroughRecorder;

it('uses the configured node binary for walkthrough recording', function (): void {
    config()->set('x-change.claim_preview.recorder.node_binary', '/runtime/node');

    Process::fake([
        '*' => Process::result(output: json_encode([
            'status' => 'recorded',
        ], JSON_THROW_ON_ERROR)),
    ]);

    $result = app(ClaimWalkthroughRecorder::class)->record(
        scenario: ['key' => 'claim-preview'],
        baseUrl: 'https://x-change.test',
        artifactDirectory: storage_path('framework/testing/claim-preview'),
        headed: false,
        slowMotion: 0,
    );

    expect($result)->toBe(['status' => 'recorded']);

    Process::assertRan(
        fn (PendingProcess $process, ProcessResult $processResult): bool => $process->command[0] === '/runtime/node'
            && str_ends_with($process->command[1], '/scripts/claim-browser-walkthrough.mjs'),
    );
});
