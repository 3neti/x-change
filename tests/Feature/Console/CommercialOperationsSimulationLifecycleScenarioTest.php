<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

function commercialSimulationActor(string $name, string $mobile): User
{
    $actor = User::query()->create([
        'name' => $name,
        'email' => str($name)->slug().'-'.str()->uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $actor->setMobileChannel($mobile);
    $actor->save();

    return $actor;
}

it('simulates governed commercial operations and rolls every record back', function (): void {
    Http::preventStrayRequests();
    enableNetbankTreasuryForTests();
    app(TreasuryProvisioningService::class)->provision(['netbank-primary']);
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:lifecycle-simulation');
    config()->set('x-change.commercial.legal_trace.profile_version', '2026-08-08.1');
    config()->set('x-change.lifecycle.commercial_operations_simulation.enabled', true);
    config()->set('x-change.lifecycle.commercial_operations_simulation.allowed_environments', ['testing']);
    $issuer = commercialSimulationActor('Commercial Simulation Issuer', '09170000001');
    $maker = commercialSimulationActor('Commercial Simulation Maker', '09170000002');
    $checker = commercialSimulationActor('Commercial Simulation Checker', '09170000003');
    $before = [
        'sales' => CommercialSale::query()->count(),
        'partners' => CommercialPartner::query()->count(),
        'commission_batches' => PartnerCommissionPayoutBatch::query()->count(),
        'journal' => ExecutionJournalEntry::query()->count(),
        'position_operations' => DB::table('treasury_position_operations')->count(),
        'inventory_operations' => DB::table('treasury_inventory_operations')->count(),
    ];

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'commercial_operations_simulation',
        '--issuer' => (string) $issuer->getKey(),
        '--maker' => (string) $maker->getKey(),
        '--checker' => (string) $checker->getKey(),
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(data_get($payload, 'schema'))->toBe('x-change.lifecycle.commercial-operations-simulation.v1')
        ->and(data_get($payload, 'success'))->toBeTrue()
        ->and(data_get($payload, 'persisted'))->toBeFalse()
        ->and(data_get($payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($payload, 'safety.external_provider_calls'))->toBeFalse()
        ->and(data_get($payload, 'safety.real_money_movement'))->toBeFalse()
        ->and(data_get($payload, 'agreement_economics'))->toMatchArray([
            'operational' => true,
            'active_profiles' => 2,
            'required_profiles' => 2,
            'recipient_authorities_active' => 1,
            'recipient_authorities_required' => 1,
            'simulation_control_policy' => 'synthetic_provider-cost-and-commission-waterfall',
        ])
        ->and(data_get($payload, 'provider_simulation.disbursement_calls'))->toBe(1)
        ->and(data_get($payload, 'provider_simulation.status_calls'))->toBe(1)
        ->and(collect(data_get($payload, 'invariants'))->every(
            static fn (bool $passed): bool => $passed,
        ))->toBeTrue()
        ->and(data_get($payload, 'partner.destination_summary'))->toContain('••••0000')
        ->and(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('09170000000')
        ->and(CommercialSale::query()->count())->toBe($before['sales'])
        ->and(CommercialPartner::query()->count())->toBe($before['partners'])
        ->and(PartnerCommissionPayoutBatch::query()->count())->toBe($before['commission_batches'])
        ->and(ExecutionJournalEntry::query()->count())->toBe($before['journal'])
        ->and(DB::table('treasury_position_operations')->count())->toBe($before['position_operations'])
        ->and(DB::table('treasury_inventory_operations')->count())->toBe($before['inventory_operations']);
});

it('requires distinct maker and checker actors', function (): void {
    enableNetbankTreasuryForTests();
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:lifecycle-simulation');
    config()->set('x-change.commercial.legal_trace.profile_version', '2026-08-08.1');
    config()->set('x-change.lifecycle.commercial_operations_simulation.enabled', true);
    config()->set('x-change.lifecycle.commercial_operations_simulation.allowed_environments', ['testing']);
    $issuer = commercialSimulationActor('Commercial Simulation Issuer Two', '09170000004');
    $operator = commercialSimulationActor('Commercial Simulation Operator', '09170000005');

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'commercial_operations_simulation',
        '--issuer' => (string) $issuer->getKey(),
        '--maker' => (string) $operator->getKey(),
        '--checker' => (string) $operator->getKey(),
        '--json' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'success' => false,
            'message' => 'Commercial maker and checker must be different people.',
            'safety' => [
                'external_provider_calls' => false,
                'real_money_movement' => false,
                'persisted' => false,
            ],
        ]);
});
