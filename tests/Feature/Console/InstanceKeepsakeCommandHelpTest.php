<?php

declare(strict_types=1);

use LBHurtado\XChange\Console\Commands\Keepsake\ExportInstanceKeepsakeCommand;
use LBHurtado\XChange\Console\Commands\Keepsake\GenerateInstanceKeepsakeKeyCommand;
use LBHurtado\XChange\Console\Commands\Keepsake\VerifyInstanceKeepsakeCommand;

it('shows actionable guidance for export command help', function () {
    $command = new ExportInstanceKeepsakeCommand;

    expect($command->getDescription())
        ->toContain('Preview or create an encrypted, non-restorable X-Change instance keepsake')
        ->and($command->getHelp())
        ->toContain('Typical flow:')
        ->toContain('Run again with --create and the exact --plan-hash')
        ->toContain('--export-reference=<OPERATOR_UNIQUE_REFERENCE>')
        ->toContain('The command never triggers provider calls');
});

it('shows actionable guidance for keygen help', function () {
    $command = new GenerateInstanceKeepsakeKeyCommand;

    expect($command->getDescription())
        ->toContain('Generate a local recipient key for encrypted X-Change instance keepsakes')
        ->and($command->getHelp())
        ->toContain('Generate a local private/public keypair used by keepsake export and verification.')
        ->toContain('Keep the generated private key outside source control')
        ->toContain('Use the printed public key in XCHANGE_INSTANCE_KEEPSAKE_PUBLIC_KEY');
});

it('shows actionable guidance for verify help', function () {
    $command = new VerifyInstanceKeepsakeCommand;

    expect($command->getDescription())
        ->toContain('Decrypt and independently verify a downloaded X-Change instance keepsake')
        ->and($command->getHelp())
        ->toContain('Decrypt and independently verify a downloaded keepsake archive')
        ->toContain('No provider calls and no financial mutations')
        ->toContain('Extraction destination must not exist');
});
