# RunLifecycleScenarioCommand Refactor Roadmap

## Done

- Extracted `ScenarioRunnerRegistry`
- Extracted settlement-specific runners:
    - `SettlementEnvelopeEvaluationScenarioRunner`
    - `SettlementThreePartyScenarioRunner`
- Extracted support utilities:
    - `SettlementScenarioSupport`
    - `LifecycleUserSummary`
    - `WalletTransactionSnapshot`
- Added settlement readiness API endpoint
- Preserved green test suite

---

# Remaining Refactors

## 1. Extract `LifecycleScenarioRepository`

Move out:

- scenario config loading
- scenario lookup
- missing scenario errors
- scenario listing
- default scenario normalization

Target class:

```php
LifecycleScenarioRepository
```

Command becomes:

```php
$scenario = $scenarios->findOrFail($scenarioKey);
```

---

## 2. Extract `LifecycleScenarioInputResolver`

Move out:

- resolving claim mobile
- issuer mobile fallback
- scenario mobile override
- selected attempt filtering
- attempt defaults

Target class:

```php
LifecycleScenarioInputResolver
```

---

## 3. Extract `LifecycleScenarioIssuerResolver`

Move out:

- resolving issuer
- creating/finding test issuer
- issuer wallet setup
- initial funding/top-up setup

Target class:

```php
LifecycleScenarioIssuerResolver
```

---

## 4. Extract `LifecycleVoucherIssuer`

Move out:

- estimate payload building
- voucher instruction payload generation
- calling `GeneratePayCode`
- voucher lookup after generation
- generated result normalization

Target class:

```php
LifecycleVoucherIssuer
```

---

## 5. Extract `DefaultClaimScenarioRunner`

Move ordinary single/multi-attempt claim execution into:

```php
DefaultClaimScenarioRunner
```

Owns:

- standard claim attempts
- success/failure expectation checking
- validation failure handling
- ordinary disbursable scenarios

This is the biggest remaining extraction.

---

## 6. Extract `SequentialClaimsScenarioRunner`

Move current repeated claim logic into:

```php
SequentialClaimsScenarioRunner
```

Owns:

- divisible vouchers
- open-slice scenarios
- enforced interval scenarios
- claim ledger summaries
- sequential claim expectations

---

## 7. Extract `CollectiblePaymentScenarioRunner`

Move collectible-specific demo behavior into:

```php
CollectiblePaymentScenarioRunner
```

Owns:

- collectible voucher issuance
- payment confirmation simulation
- collection progress
- fully collected / partially collected assertions
- no-withdrawal behavior

---

## 8. Extract `ReconciliationScenarioRunner`

Move reconciliation scenarios into:

```php
ReconciliationScenarioRunner
```

Owns:

- pending disbursement simulation
- provider success/failure replay
- reconciliation resolution
- reconciliation result expectations

---

## 9. Extract `LifecycleExpectationEvaluator`

Move expectation checking out of the command/runners.

Target:

```php
LifecycleExpectationEvaluator
```

Owns:

- expected status vs actual status
- expected message/code
- expected claim type
- expected wallet effect
- expected settlement state
- expected reconciliation state

Runners should produce facts. Evaluator should judge them.

---

## 10. Extract `LifecycleResultRenderer`

Move all console rendering into:

```php
LifecycleResultRenderer
```

Owns:

- normal console output
- JSON output
- attempts summary
- claim summary
- wallet transaction tables
- settlement phase rendering

Command should only do:

```php
return $renderer->render($result, $this);
```

---

## 11. Introduce `ScenarioRunContext`

Replace the growing runner signature:

```php
run(
    Command $command,
    string $scenarioKey,
    array $scenario,
    Model $issuer,
    mixed $generated,
    mixed $voucher,
    array $attempts,
    string $baseClaimMobile,
    array $estimate,
    string $idempotencyKey,
)
```

With:

```php
run(ScenarioRunContext $context): ScenarioRunResult
```

Context contains:

```php
scenarioKey
scenario
options
issuer
generated
voucher
attempts
baseClaimMobile
estimate
idempotencyKey
```

This makes future runners much cleaner.

---

## 12. Normalize `ScenarioRunResult`

Current result is:

```php
exitCode
payload
```

Eventually add:

```php
mode
passed
summary
facts
errors
warnings
```

Target:

```php
ScenarioRunResult
```

Still renderable as the same JSON payload.

---

## 13. Make Registry Configurable

Current registry can stay hardcoded for now.

Later:

```php
'life_cycle_scenario_runners' => [
    'settlement_envelope_evaluation' => SettlementEnvelopeEvaluationScenarioRunner::class,
    'settlement_three_party_flow' => SettlementThreePartyScenarioRunner::class,
]
```

Then package users can register custom scenario modes.

---

## Recommended Next Slice

Do this next:

```text
Extract ScenarioRunContext
```

Why:

- low risk
- makes every future runner extraction cleaner
- removes long parameter lists
- does not require changing behavior
- prepares for `DefaultClaimScenarioRunner`

After that:

```text
Extract DefaultClaimScenarioRunner
```

Then:

```text
Extract SequentialClaimsScenarioRunner
```

---

# Final Target Shape

```php
final class RunLifecycleScenarioCommand extends Command
{
    public function handle(
        LifecycleScenarioRepository $scenarios,
        LifecycleScenarioBootstrapper $bootstrapper,
        ScenarioRunnerRegistry $runners,
        LifecycleResultRenderer $renderer,
    ): int {
        $scenario = $scenarios->resolve(
            key: $this->argument('scenario'),
            options: $this->options(),
        );

        $context = $bootstrapper->build($scenario, $this);

        $result = $runners
            ->for($context->mode())
            ->run($context);

        return $renderer->render($result, $this);
    }
}
```

That is the destination.
