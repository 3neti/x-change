<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use LBHurtado\XChange\Services\Configuration\CommercialPrincipalAccountReadinessInspector;

beforeEach(function (): void {
    config()->set('x-change.commercial.billing.mode', 'billable');
    config()->set('x-change.commercial.principal.reference', 'commercial-alpha');
    config()->set('x-change.commercial.principal.legal_name', '3neti R&D OPC');
    config()->set(
        'x-change.commercial.principal.authorization_reference',
        'commissioning:commercial-alpha:v1',
    );
    config()->set(
        'x-change.commercial.principal.revenue_account_slug',
        'commercial-revenue',
    );
});

it('previews commercial-principal creation without mutation', function (): void {
    $this->artisan('x-change:commercial-principal:provision', [
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"would_create"')
        ->assertExitCode(Command::SUCCESS);

    expect(CommercialPrincipal::query()->count())->toBe(0);
});

it('requires explicit confirmation before committing', function (): void {
    $this->artisan('x-change:commercial-principal:provision', [
        '--json' => true,
        '--commit' => true,
    ])
        ->expectsOutputToContain('--confirm-commercial-principal')
        ->assertFailed();

    expect(CommercialPrincipal::query()->count())->toBe(0);
});

it('provisions a non-login commercial principal and revenue Account idempotently', function (): void {
    $arguments = [
        '--commit' => true,
        '--confirm-commercial-principal' => true,
        '--json' => true,
    ];

    $this->artisan('x-change:commercial-principal:provision', $arguments)
        ->expectsOutputToContain('"status":"provisioned"')
        ->assertSuccessful();

    $principal = CommercialPrincipal::query()->sole();

    $second = app(CommercialPrincipalProvisioningService::class)->provision();

    expect(CommercialPrincipal::query()->count())->toBe(1)
        ->and($second->status)->toBe('existing_ready')
        ->and($principal->wallets()->where('slug', 'commercial-revenue')->count())->toBe(1)
        ->and($principal->legal_name)->toBe('3neti R&D OPC')
        ->and($principal->active)->toBeTrue()
        ->and(data_get($principal->metadata, 'interactive_login'))->toBeFalse()
        ->and($principal)->toBeInstanceOf(Authenticatable::class)
        ->and($principal)->toBeInstanceOf(Customer::class)
        ->and((string) $principal->getAuthIdentifier())->toBe((string) $principal->getKey())
        ->and($principal->getAuthPassword())->toBeNull()
        ->and($principal->getAttribute('email'))->toBeNull()
        ->and($principal->getAttribute('mobile'))->toBeNull()
        ->and(config('auth.providers.users.model'))->not->toBe(CommercialPrincipal::class);

    $check = app(CommercialPrincipalAccountReadinessInspector::class)->inspect();

    expect($check['passed'])->toBeTrue()
        ->and($check['meta']['routing_state'])->toBe('pending_controlled_migration');
});

it('fails closed when the persisted principal conflicts with configuration', function (): void {
    CommercialPrincipal::query()->create([
        'reference' => 'commercial-alpha',
        'legal_name' => 'Different Entity',
        'authorization_reference' => 'commissioning:commercial-alpha:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);

    $this->artisan('x-change:commercial-principal:provision', [
        '--commit' => true,
        '--confirm-commercial-principal' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('different legal name')
        ->assertFailed();
});

it('does not require a commercial Account in informational billing mode', function (): void {
    config()->set('x-change.commercial.billing.mode', 'informational');

    $check = app(CommercialPrincipalAccountReadinessInspector::class)->inspect();

    expect($check['passed'])->toBeTrue()
        ->and($check['meta']['required'])->toBeFalse()
        ->and(CommercialPrincipal::query()->count())->toBe(0);
});
