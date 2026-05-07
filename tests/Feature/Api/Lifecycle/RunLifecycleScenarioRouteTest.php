<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function prepareApiLifecycleIssuer(): FakeLifecycleUser
{
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);

    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Lifecycle Issuer',
        'email' => 'issuer@example.test',
        'password' => bcrypt('password'),
    ]);

    $issuer->setMobileChannel('09171234567');
    $issuer->save();

    fundTestUserWallet($issuer);

    return $issuer;
}

it('runs a lifecycle scenario through the lifecycle API (no-claim fast path)', function () {
    $issuer = prepareApiLifecycleIssuer();

    $response = $this->postJson(xchangeApi('lifecycle/scenarios/run'), [
        'scenario' => 'secret_required',
        'issuer' => (string) $issuer->getKey(),
        'wallet' => (string) $issuer->getKey(),
        'no_claim' => true,
        'timeout' => 1,
        'poll' => 1,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'scenario',
            'label',
            'selected_attempt',
            'issuer',
            'claim_mobile',
            'attempts',
            'attempt_summary' => [
                'passed',
                'failed',
                'total',
            ],
            'estimate',
            'generated' => [
                'code',
            ],
            'wallet_transactions',
        ]);

    expect($response->json('scenario'))->toBe('secret_required')
        ->and(is_string($response->json('generated.code')))->toBeTrue();
});

it('returns validation error when scenario is missing', function () {
    $response = $this->postJson(xchangeApi('lifecycle/scenarios/run'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['scenario']);
});

it('returns 422 for unknown lifecycle scenario', function () {
    $response = $this->postJson(xchangeApi('lifecycle/scenarios/run'), [
        'scenario' => 'unknown_scenario_key',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);

    expect($response->json('message'))->toContain('Unknown');
});

it('passes runtime options to the engine', function () {
    $issuer = prepareApiLifecycleIssuer();

    $response = $this->postJson(xchangeApi('lifecycle/scenarios/run'), [
        'scenario' => 'secret_required',
        'issuer' => (string) $issuer->getKey(),
        'wallet' => (string) $issuer->getKey(),
        'only_attempt' => 'correct_secret_succeeds',
        'timeout' => 2,
        'poll' => 1,
        'accept_pending' => true,
    ]);

    $response->assertOk();

    expect($response->json('selected_attempt'))->toBe('correct_secret_succeeds');

    expect(Arr::get($response->json('attempt_summary'), 'total'))->toBeGreaterThanOrEqual(1);
});
