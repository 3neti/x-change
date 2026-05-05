<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\ScenarioRunContext;

it('exposes scenario metadata helpers', function () {
    $command = new class extends Command {
        protected $signature = 'test:scenario-context';

        public function option($key = null): mixed
        {
            return match ($key) {
                'only-attempt' => 'blocked',
                'json' => true,
                default => null,
            };
        }
    };

    $issuer = new class extends Model {};
    $voucher = (object) ['code' => 'ABC123'];
    $generated = (object) ['code' => 'ABC123'];

    $context = new ScenarioRunContext(
        command: $command,
        scenarioKey: 'settlement_philhealth_bst',
        scenario: [
            'label' => 'Settlement PhilHealth BST',
            'mode' => 'settlement_envelope_evaluation',
        ],
        issuer: $issuer,
        generated: $generated,
        voucher: $voucher,
        attempts: [
            'blocked' => [],
        ],
        baseClaimMobile: '639178251991',
        estimate: [
            'total' => 100,
        ],
        idempotencyKey: 'test-key',
    );

    expect($context->mode())->toBe('settlement_envelope_evaluation')
        ->and($context->label())->toBe('Settlement PhilHealth BST')
        ->and($context->selectedAttempt())->toBe('blocked')
        ->and($context->wantsJson())->toBeTrue();
});

it('falls back to scenario key as label', function () {
    $command = new class extends Command {
        protected $signature = 'test:scenario-context-fallback';

        public function option($key = null): mixed
        {
            return null;
        }
    };

    $context = new ScenarioRunContext(
        command: $command,
        scenarioKey: 'basic_cash',
        scenario: [],
        issuer: new class extends Model {},
        generated: (object) [],
        voucher: (object) [],
        attempts: [],
        baseClaimMobile: '639178251991',
        estimate: [],
        idempotencyKey: 'test-key',
    );

    expect($context->mode())->toBeNull()
        ->and($context->label())->toBe('basic_cash')
        ->and($context->selectedAttempt())->toBeNull()
        ->and($context->wantsJson())->toBeFalse();
});
