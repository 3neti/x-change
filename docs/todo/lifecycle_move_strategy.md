# Lifecycle Runtime Move Strategy

Source tree checked. The lifecycle runtime files are currently concentrated under:

```text
src/Console/Commands/Lifecycle/ScenarioRunners
src/Console/Commands/Lifecycle/ScenarioRunners/Support
```

The existing command/controller files are already in their intended adapter locations:

```text
src/Console/Commands/Lifecycle/RunLifecycleScenarioCommand.php
src/Console/Commands/Lifecycle/PrepareLifecycleEnvironmentCommand.php
src/Http/Controllers/Lifecycle/RunLifecycleScenarioController.php
```

So the safest strategy is to move the runtime internals first, while leaving the console and HTTP adapters in place. The uploaded tree confirms the current file placements and names.

---

# Recommended Move Order

## Slice 1 — Move Output Layer First

This is the least risky because these are adapter-style classes and have the fewest conceptual dependencies.

Move from:

```text
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleOutputContract.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/ConsoleLifecycleOutput.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/NullLifecycleOutput.php
```

Move to:

```text
src/Lifecycle/Output/LifecycleOutputContract.php
src/Lifecycle/Output/ConsoleLifecycleOutput.php
src/Lifecycle/Output/NullLifecycleOutput.php
```

Create new:

```text
src/Lifecycle/Output/BufferedLifecycleOutput.php
```

New namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Output;
```

Why first:

- Very clear grouping.
- Minimal domain impact.
- Command and API can import the new output namespace.
- Helps remove output concerns from `ScenarioRunners\Support`.

Run after move:

```bash
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioCommandTest.php
./vendor/bin/pest tests/Feature/Api/Lifecycle/RunLifecycleScenarioRouteTest.php
```

---

## Slice 2 — Move Scenario Core

Move from:

```text
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioEngine.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioRunOptions.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioEngineResult.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioRepository.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioBootstrapper.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioBootstrapResult.php
```

Move to:

```text
src/Lifecycle/Scenarios/LifecycleScenarioEngine.php
src/Lifecycle/Scenarios/LifecycleScenarioRunOptions.php
src/Lifecycle/Scenarios/LifecycleScenarioEngineResult.php
src/Lifecycle/Scenarios/LifecycleScenarioRepository.php
src/Lifecycle/Scenarios/LifecycleScenarioBootstrapper.php
src/Lifecycle/Scenarios/LifecycleScenarioBootstrapResult.php
```

New namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Scenarios;
```

Why second:

- These are the actual scenario orchestration classes.
- They likely depend on runners and output, but they conceptually belong above runners.
- Moving them after Output reduces one dependency namespace change.

Run after move:

```bash
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioEngineTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioCommandTest.php
./vendor/bin/pest tests/Feature/Api/Lifecycle/RunLifecycleScenarioRouteTest.php
```

---

## Slice 3 — Move Runner Core

Move from:

```text
src/Console/Commands/Lifecycle/ScenarioRunners/ScenarioRunnerContract.php
src/Console/Commands/Lifecycle/ScenarioRunners/ScenarioRunContext.php
src/Console/Commands/Lifecycle/ScenarioRunners/ScenarioRunResult.php
src/Console/Commands/Lifecycle/ScenarioRunners/ScenarioRunnerResolver.php
src/Console/Commands/Lifecycle/ScenarioRunners/ScenarioRunnerRegistry.php
src/Console/Commands/Lifecycle/ScenarioRunners/ScenarioRunnerResolution.php

src/Console/Commands/Lifecycle/ScenarioRunners/DefaultClaimScenarioRunner.php
src/Console/Commands/Lifecycle/ScenarioRunners/SequentialClaimsScenarioRunner.php
src/Console/Commands/Lifecycle/ScenarioRunners/SettlementEnvelopeEvaluationScenarioRunner.php
src/Console/Commands/Lifecycle/ScenarioRunners/SettlementThreePartyScenarioRunner.php
```

Move to:

```text
src/Lifecycle/Runners/ScenarioRunnerContract.php
src/Lifecycle/Runners/ScenarioRunContext.php
src/Lifecycle/Runners/ScenarioRunResult.php
src/Lifecycle/Runners/ScenarioRunnerResolver.php
src/Lifecycle/Runners/ScenarioRunnerRegistry.php
src/Lifecycle/Runners/ScenarioRunnerResolution.php

src/Lifecycle/Runners/DefaultClaimScenarioRunner.php
src/Lifecycle/Runners/SequentialClaimsScenarioRunner.php
src/Lifecycle/Runners/SettlementEnvelopeEvaluationScenarioRunner.php
src/Lifecycle/Runners/SettlementThreePartyScenarioRunner.php
```

New namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Runners;
```

Why third:

- Runner classes will import the newly moved `Scenarios` and `Output` classes.
- Moving them after scenario/output keeps dependency direction readable.

Run after move:

```bash
./vendor/bin/pest tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSettlementEnvelopeScenarioTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSettlementThreePartyScenarioTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioCommandTest.php
```

---

## Slice 4 — Move Runner Support

Move from:

```text
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleClaimAttemptEvaluator.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleClaimResultNormalizer.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleDisbursementPoller.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleUserSummary.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/SettlementEnvelopeContextBuilder.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/SettlementEnvelopePersister.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/SettlementPhaseSummary.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/SettlementScenarioSupport.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/WalletTransactionSnapshot.php
```

Move to:

```text
src/Lifecycle/Runners/Support/LifecycleClaimAttemptEvaluator.php
src/Lifecycle/Runners/Support/LifecycleClaimResultNormalizer.php
src/Lifecycle/Runners/Support/LifecycleDisbursementPoller.php
src/Lifecycle/Runners/Support/LifecycleUserSummary.php
src/Lifecycle/Runners/Support/SettlementEnvelopeContextBuilder.php
src/Lifecycle/Runners/Support/SettlementEnvelopePersister.php
src/Lifecycle/Runners/Support/SettlementPhaseSummary.php
src/Lifecycle/Runners/Support/SettlementScenarioSupport.php
src/Lifecycle/Runners/Support/WalletTransactionSnapshot.php
```

New namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Runners\Support;
```

Why fourth:

- These are helper classes used by runners.
- They may have cross-imports to runner/result/context classes.
- Moving them after runner core makes IDE namespace correction easier.

Run after move:

```bash
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioCommandTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSettlementEnvelopeScenarioTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSettlementThreePartyScenarioTest.php
```

---

## Slice 5 — Handle Leftover File: LifecycleResultRenderer

Current tree includes:

```text
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleResultRenderer.php
```

This file is not in the target structure you listed.

Recommended placement:

```text
src/Lifecycle/Output/LifecycleResultRenderer.php
```

Recommended namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Output;
```

Reason:

- It sounds like presentation/output concern.
- It should not remain under runner support.
- It should sit beside `ConsoleLifecycleOutput`, `NullLifecycleOutput`, and `BufferedLifecycleOutput`.

Alternative if it is strictly console-only:

```text
src/Console/Commands/Lifecycle/LifecycleResultRenderer.php
```

But my recommendation is `src/Lifecycle/Output`.

---

# Final Target Structure

```text
src/
├── Lifecycle/
│   ├── Scenarios/
│   │   ├── LifecycleScenarioEngine.php
│   │   ├── LifecycleScenarioRunOptions.php
│   │   ├── LifecycleScenarioEngineResult.php
│   │   ├── LifecycleScenarioRepository.php
│   │   ├── LifecycleScenarioBootstrapper.php
│   │   └── LifecycleScenarioBootstrapResult.php
│   │
│   ├── Runners/
│   │   ├── ScenarioRunnerContract.php
│   │   ├── ScenarioRunContext.php
│   │   ├── ScenarioRunResult.php
│   │   ├── ScenarioRunnerResolver.php
│   │   ├── ScenarioRunnerRegistry.php
│   │   ├── ScenarioRunnerResolution.php
│   │   ├── DefaultClaimScenarioRunner.php
│   │   ├── SequentialClaimsScenarioRunner.php
│   │   ├── SettlementEnvelopeEvaluationScenarioRunner.php
│   │   ├── SettlementThreePartyScenarioRunner.php
│   │   └── Support/
│   │       ├── LifecycleClaimAttemptEvaluator.php
│   │       ├── LifecycleClaimResultNormalizer.php
│   │       ├── LifecycleDisbursementPoller.php
│   │       ├── LifecycleUserSummary.php
│   │       ├── SettlementEnvelopeContextBuilder.php
│   │       ├── SettlementEnvelopePersister.php
│   │       ├── SettlementPhaseSummary.php
│   │       ├── SettlementScenarioSupport.php
│   │       └── WalletTransactionSnapshot.php
│   │
│   └── Output/
│       ├── LifecycleOutputContract.php
│       ├── ConsoleLifecycleOutput.php
│       ├── NullLifecycleOutput.php
│       ├── BufferedLifecycleOutput.php
│       └── LifecycleResultRenderer.php
```

---

# Do Not Move Yet

Leave these where they are for now:

```text
src/Console/Commands/Lifecycle/RunLifecycleScenarioCommand.php
src/Console/Commands/Lifecycle/PrepareLifecycleEnvironmentCommand.php
src/Http/Controllers/Lifecycle/RunLifecycleScenarioController.php
```

Reason:

- They are already adapters.
- They are already in the desired layer.
- Only their imports should change.

---

# Practical Refactor Sequence

```text
1. Create target folders
2. Move Output classes
3. Run focused tests
4. Move Scenario core
5. Run focused tests
6. Move Runner core
7. Run focused tests
8. Move Runner support
9. Move LifecycleResultRenderer to Output
10. Run full lifecycle test group
11. Run full suite
```

Suggested final test command:

```bash
./vendor/bin/pest tests/Feature/Console tests/Feature/Api/Lifecycle tests/Unit
```

If that is too broad, start with:

```bash
./vendor/bin/pest \
  tests/Feature/Console/LifecycleScenarioCommandTest.php \
  tests/Feature/Console/LifecycleScenarioEngineTest.php \
  tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php \
  tests/Feature/Console/LifecycleSettlementEnvelopeScenarioTest.php \
  tests/Feature/Console/LifecycleSettlementThreePartyScenarioTest.php \
  tests/Feature/Api/Lifecycle/RunLifecycleScenarioRouteTest.php
```

---

# Commit Strategy

Use one commit if IDE namespace updates are clean:

```text
refactor: move lifecycle scenario runtime to first-class namespace
```

Use multiple commits if you want safer rollback:

```text
refactor: move lifecycle output adapters
refactor: move lifecycle scenario core
refactor: move lifecycle scenario runners
refactor: move lifecycle runner support services
```

---

# Recommended First Slice

Start with:

```text
LifecycleOutputContract.php
ConsoleLifecycleOutput.php
NullLifecycleOutput.php
LifecycleResultRenderer.php
```

Because this is the least conceptually risky and immediately clarifies that output/rendering is not runner logic.
