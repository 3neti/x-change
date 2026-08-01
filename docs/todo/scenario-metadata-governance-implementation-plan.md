# Scenario Metadata Governance — Implementation Plan

## Goal

Add first-class metadata to every lifecycle scenario so scenarios can later be filtered, grouped, documented, and certified.

This prepares the runtime for:

```bash
php artisan xchange:lifecycle:run-group smoke
php artisan xchange:lifecycle:run-group contract
php artisan xchange:lifecycle:run-group pre-deployment
```

---

# 1. Metadata Fields

Every lifecycle scenario should eventually include:

```php
'label' => 'Human readable label',
'category' => 'smoke|contract|provider|settlement|reconciliation|partner|regression',
'mode' => 'default|sequential_claims|settlement_envelope_evaluation|settlement_three_party_flow',
'tags' => ['voucher', 'wallet'],
'risk' => 'low|medium|high',
'description' => 'What this scenario proves.',
```

---

# 2. Recommended Categories

Use these canonical categories:

```php
smoke
contract
provider
settlement
reconciliation
partner
regression
```

Suggested meanings:

```text
smoke          quick confidence checks
contract       business rule / validation scenarios
provider       EMI, payout, payment, QR, webhook provider scenarios
settlement     settlement envelope and multi-party flows
reconciliation failed/pending/recovered provider outcome scenarios
partner        onboarding / sandbox certification scenarios
regression     broader release candidate scenarios
```

---

# 3. Recommended Risk Levels

Use:

```php
low
medium
high
```

Suggested meanings:

```text
low     safe, no external money movement, local/synthetic
medium  exercises wallet/claim/disbursement abstractions
high    provider-facing, settlement-facing, reconciliation-facing, or partner certification
```

---

# 4. Backward Compatibility Rule

Do not break existing scenarios immediately.

`LifecycleScenarioRepository` should tolerate missing metadata by applying defaults:

```php
category: smoke
tags: []
risk: medium
description: ''
```

This allows incremental updates.

---

# 5. Add Metadata Normalization to LifecycleScenarioRepository

File:

```text
src/Lifecycle/Scenarios/LifecycleScenarioRepository.php
```

Add methods similar to:

```php
public function all(): array
{
    return collect($this->scenarios())
        ->map(fn (array $scenario, string $key) => $this->normalize($key, $scenario))
        ->all();
}

public function find(string $key): ?array
{
    $scenario = data_get($this->scenarios(), $key);

    if (! is_array($scenario)) {
        return null;
    }

    return $this->normalize($key, $scenario);
}

public function byCategory(string $category): array
{
    return collect($this->all())
        ->filter(fn (array $scenario) => $scenario['category'] === $category)
        ->all();
}

public function byTag(string $tag): array
{
    return collect($this->all())
        ->filter(fn (array $scenario) => in_array($tag, $scenario['tags'] ?? [], true))
        ->all();
}

public function groupedByCategory(): array
{
    return collect($this->all())
        ->groupBy('category')
        ->map(fn ($items) => $items->all())
        ->all();
}

private function normalize(string $key, array $scenario): array
{
    $scenario['key'] = $scenario['key'] ?? $key;
    $scenario['label'] = $scenario['label'] ?? str($key)->replace('_', ' ')->title()->toString();
    $scenario['category'] = $scenario['category'] ?? 'smoke';
    $scenario['mode'] = $scenario['mode'] ?? null;
    $scenario['tags'] = array_values($scenario['tags'] ?? []);
    $scenario['risk'] = $scenario['risk'] ?? 'medium';
    $scenario['description'] = $scenario['description'] ?? '';

    return $scenario;
}
```

Adjust to match the current constructor/config-loading style.

---

# 6. Add Repository Tests

Likely file:

```text
tests/Unit/Lifecycle/Scenarios/LifecycleScenarioRepositoryTest.php
```

If current tests still live under old console namespace, you may use:

```text
tests/Unit/Console/Lifecycle/ScenarioRepositoryTest.php
```

Add tests:

```php
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;

it('normalizes scenario metadata defaults', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'label' => 'Basic Cash',
        ],
    ]);

    $scenario = app(LifecycleScenarioRepository::class)->find('basic_cash');

    expect($scenario)
        ->not->toBeNull()
        ->and($scenario['key'])->toBe('basic_cash')
        ->and($scenario['label'])->toBe('Basic Cash')
        ->and($scenario['category'])->toBe('smoke')
        ->and($scenario['tags'])->toBe([])
        ->and($scenario['risk'])->toBe('medium')
        ->and($scenario['description'])->toBe('');
});

it('finds scenarios by category', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'category' => 'smoke',
        ],
        'secret_required' => [
            'category' => 'contract',
        ],
    ]);

    $repository = app(LifecycleScenarioRepository::class);

    expect(array_keys($repository->byCategory('contract')))
        ->toBe(['secret_required']);
});

it('finds scenarios by tag', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'secret_required' => [
            'tags' => ['voucher', 'redemption', 'validation'],
        ],
        'wallet_debit_smoke' => [
            'tags' => ['wallet'],
        ],
    ]);

    $repository = app(LifecycleScenarioRepository::class);

    expect(array_keys($repository->byTag('wallet')))
        ->toBe(['wallet_debit_smoke']);
});

it('groups scenarios by category', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'category' => 'smoke',
        ],
        'secret_required' => [
            'category' => 'contract',
        ],
    ]);

    $groups = app(LifecycleScenarioRepository::class)->groupedByCategory();

    expect($groups)
        ->toHaveKey('smoke')
        ->toHaveKey('contract');
});
```

---

# 7. Update Existing Scenario Config

Find lifecycle scenario config, likely in:

```text
config/x-change.php
```

or:

```text
config/x-change-lifecycle.php
```

Update each scenario gradually.

## Smoke Examples

```php
'basic_cash' => [
    'label' => 'Basic Cash',
    'category' => 'smoke',
    'tags' => ['voucher', 'issuance', 'claim'],
    'risk' => 'low',
    'description' => 'Issues a basic cash Pay Code and verifies the base lifecycle path.',
    // existing scenario fields...
],

'collectible_basic_payment' => [
    'label' => 'Collectible Basic Payment',
    'category' => 'smoke',
    'tags' => ['voucher', 'collectible', 'payment', 'wallet'],
    'risk' => 'medium',
    'description' => 'Proves a collectible voucher can receive payment and update collection progress.',
    // existing scenario fields...
],
```

## Contract Examples

```php
'secret_required' => [
    'label' => 'Secret Required Redemption',
    'category' => 'contract',
    'tags' => ['voucher', 'redemption', 'validation', 'secret'],
    'risk' => 'medium',
    'description' => 'Proves a voucher requiring a secret cannot be redeemed without the correct secret.',
    // existing scenario fields...
],

'mobile_locked_contract' => [
    'label' => 'Mobile Locked Contract',
    'category' => 'contract',
    'tags' => ['voucher', 'redemption', 'validation', 'mobile'],
    'risk' => 'medium',
    'description' => 'Proves a voucher restricted to one mobile number rejects other claimants.',
    // existing scenario fields...
],
```

## Settlement Examples

```php
'settlement_philhealth_bst_blocked' => [
    'label' => 'PhilHealth BST Settlement Blocked',
    'category' => 'settlement',
    'tags' => ['settlement', 'settlement-envelope', 'philhealth-bst'],
    'risk' => 'high',
    'description' => 'Proves a PhilHealth BST settlement voucher remains blocked before required evidence is complete.',
    // existing scenario fields...
],

'settlement_philhealth_bst_three_party' => [
    'label' => 'PhilHealth BST Three-Party Settlement',
    'category' => 'settlement',
    'tags' => ['settlement', 'settlement-envelope', 'philhealth-bst', 'attestation', 'collection'],
    'risk' => 'high',
    'description' => 'Proves patient attestation, settlement payment, and envelope readiness work together.',
    // existing scenario fields...
],
```

## Reconciliation Examples

```php
'reconciliation_provider_failure' => [
    'label' => 'Provider Failure Reconciliation',
    'category' => 'reconciliation',
    'tags' => ['reconciliation', 'provider', 'disbursement'],
    'risk' => 'high',
    'description' => 'Proves failed provider outcomes create reconciliation records for review.',
    // existing scenario fields...
],

'reconciliation_resolved_success' => [
    'label' => 'Resolved Reconciliation Success',
    'category' => 'reconciliation',
    'tags' => ['reconciliation', 'provider', 'resolution'],
    'risk' => 'high',
    'description' => 'Proves a reconciliation record can be resolved after provider outcome correction.',
    // existing scenario fields...
],
```

---

# 8. Optional Constants

To avoid string drift, you may add a small enum later.

Not necessary for this slice, but possible:

```text
src/Lifecycle/Scenarios/LifecycleScenarioCategory.php
src/Lifecycle/Scenarios/LifecycleScenarioRisk.php
```

For now, plain strings are fine.

Reason:

- Less churn.
- Faster adoption.
- Easier config editing.

---

# 9. Do Not Add Group Runner Yet

Do not add:

```text
xchange:lifecycle:run-group
```

yet.

That belongs to the next slice.

This slice should only make scenarios discoverable and classifiable.

---

# 10. Test Commands

Run focused tests first:

```bash
./vendor/bin/pest tests/Unit/Lifecycle/Scenarios/LifecycleScenarioRepositoryTest.php
```

Then lifecycle tests:

```bash
./vendor/bin/pest \
  tests/Feature/Console/LifecycleScenarioCommandTest.php \
  tests/Feature/Console/LifecycleScenarioEngineTest.php \
  tests/Feature/Api/Lifecycle/RunLifecycleScenarioRouteTest.php
```

Then full suite:

```bash
./vendor/bin/pest
```

---

# 11. Definition of Done

This slice is done when:

- every scenario returned by repository has:
    - key
    - label
    - category
    - tags
    - risk
    - description
- missing metadata is safely defaulted
- repository can filter by category
- repository can filter by tag
- repository can group by category
- existing lifecycle command still works
- lifecycle API route still works
- all tests pass

---

# 12. Commit Message

```text
feat: add lifecycle scenario metadata governance
```
