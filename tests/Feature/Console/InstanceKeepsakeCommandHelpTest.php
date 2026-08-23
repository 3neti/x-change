<?php

declare(strict_types=1);

it('shows actionable guidance for export command help', function () {
    $this->artisan('x-change:instance-keepsake:export', ['--help' => true])
        ->expectsOutputToContain('Preview or create an encrypted, non-restorable X-Change instance keepsake.')
        ->expectsOutputToContain('Typical flow:')
        ->expectsOutputToContain('--create : Create and privately stage the encrypted archive')
        ->expectsOutputToContain('--plan-hash= : Exact plan hash from a dry-run')
        ->expectsOutputToContain('The command never triggers provider calls')
        ->assertSuccessful();
});

it('shows actionable guidance for keygen help', function () {
    $this->artisan('x-change:instance-keepsake:keygen', ['--help' => true])
        ->expectsOutputToContain('Generate a local private/public keypair used by keepsake export and verification.')
        ->expectsOutputToContain('Keep the generated private key outside source control')
        ->expectsOutputToContain('Use the printed public key in XCHANGE_INSTANCE_KEEPSAKE_PUBLIC_KEY')
        ->assertSuccessful();
});

it('shows actionable guidance for verify help', function () {
    $this->artisan('x-change:instance-keepsake:verify', ['--help' => true])
        ->expectsOutputToContain('Decrypt and independently verify a downloaded keepsake archive')
        ->expectsOutputToContain('No provider calls and no financial mutations')
        ->expectsOutputToContain('Extraction destination must not exist')
        ->assertSuccessful();
});
