# Strategy and Plan: Refactor `RunLifecycleScenarioCommand`

## Diagnosis

`RunLifecycleScenarioCommand.php` has reached the natural limit of scaffolding.

At ~1,900+ lines, it is now doing too many jobs:

- scenario resolution
- defaults merging
- command option handling
- issuer and wallet resolution
- voucher cost estimation
- voucher generation
- claim execution
- attempt filtering
- settlement envelope evaluation
- three-party settlement simulation
- demo/semantic rendering
- persistence side effects
- JSON and console output formatting

This is acceptable for proving lifecycle behavior, but it is becoming fragile as a long-term architecture.

The biggest smell is this:

```php
if (($scenario['mode'] ?? null) === 'settlement_envelope_evaluation') {
    return $this->runSettlementEnvelopeScenario(...);
}

if (($scenario['mode'] ?? null) === 'settlement_three_party_flow') {
    return $this->runSettlementThreePartyScenario(...);
}
```

Every new lifecycle mode will add another branch and another large method. That means the command will keep growing, and scenario-specific semantics will keep leaking into the console layer.

## Strategic Direction

The command should become a thin orchestrator.

Future shape:

```text
RunLifecycleScenarioCommand
    → resolves scenario
    → builds ScenarioRunContext
    → delegates to ScenarioRunnerRegistry
    → renders ScenarioRunResult
```

Mode-specific logic should move into dedicated runner classes:

```text
DefaultClaimScenarioRunner
SettlementEnvelopeEvaluationScenarioRunner
SettlementThreePartyScenarioRunner
CollectiblePaymentScenarioRunner
ReconciliationScenarioRunner
```

The command should not know how a settlement three-party flow works. It should only know how to locate the correct runner and display the result.

## Target Architecture

```text
Console Command
    RunLifecycleScenarioCommand
        |
        v
Scenario Resolution Layer
    LifecycleScenarioRepository
    LifecycleScenarioDefaultsMerger
    ScenarioAttemptFilter
        |
        v
Execution Layer
    ScenarioRunnerRegistry
    ScenarioRunnerContract
        |
        +-- DefaultClaimScenarioRunner
        +-- SequentialClaimsScenarioRunner
        +-- SettlementEnvelopeEvaluationScenarioRunner
        +-- SettlementThreePartyScenarioRunner
        +-- CheckOnlyScenarioRunner
        |
        v
Shared Support Layer
    LifecycleVoucherIssuer
    LifecycleClaimSubmitter
    LifecycleExpectationEvaluator
    LifecycleResultFormatter
```

## Proposed Contract

```php
interface ScenarioRunnerContract
{
    public function supports(LifecycleScenarioData $scenario): bool;

    public function run(LifecycleScenarioRunContext $context): LifecycleScenarioRunResultData;
}
```

## Proposed Context DTO

```php
final class LifecycleScenarioRunContext
{
    public function __construct(
        public string $scenarioKey,
        public array $scenario,
        public array $options,
        public ?Model $issuer = null,
        public ?object $generated = null,
        public mixed $voucher = null,
        public ?string $idempotencyKey = null,
    ) {}
}
```

## Proposed Result DTO

```php
final class LifecycleScenarioRunResultData
{
    public function __construct(
        public string $scenario,
        public string $label,
        public string $mode,
        public bool $passed,
        public array $payload = [],
        public int $exitCode = 0,
    ) {}
}
```

## Refactor Plan

### Phase 1 — Stabilize Before Extracting

Do not rewrite the command immediately.

First, add characterization tests around the current command behavior:

- `basic_cash`
- `bio`
- `otp`
- divisible withdraw scenario
- `settlement_philhealth_bst`
- `settlement_philhealth_bst_three_party`
- `--json`
- `--only-attempt`
- `--no-claim`
- `--check-only`

Goal: make sure refactoring does not change behavior.

## Phase 2 — Extract Result Rendering

Move output formatting first because it is low risk.

Create:

```text
LifecycleScenarioResultRenderer
```

Responsibilities:

- JSON output
- human console output
- attempt summaries
- claim summaries
- wallet transaction display
- settlement semantic display

The command keeps calling:

```php
return $renderer->render($result, $this);
```

## Phase 3 — Extract Scenario Loading

Create:

```text
LifecycleScenarioRepository
LifecycleScenarioDefaultsMerger
ScenarioAttemptFilter
```

Move out:

- `resolveScenario`
- `listScenarios`
- defaults merging
- attempt normalization
- claim normalization
- `--only-attempt` filtering

The command becomes responsible only for receiving the scenario key.

## Phase 4 — Introduce Runner Registry

Create:

```text
ScenarioRunnerRegistry
```

The registry maps scenario mode to runner:

```php
[
    'default' => DefaultClaimScenarioRunner::class,
    'settlement_envelope_evaluation' => SettlementEnvelopeEvaluationScenarioRunner::class,
    'settlement_three_party_flow' => SettlementThreePartyScenarioRunner::class,
]
```

Resolution rule:

```php
$mode = $scenario['mode'] ?? 'default';
$runner = $registry->for($mode);
```

This removes mode branches from the command.

## Phase 5 — Extract Default Claim Runner

Move ordinary voucher generation and claim execution into:

```text
DefaultClaimScenarioRunner
```

Owns:

- estimate cost
- generate voucher
- submit claim
- evaluate expectation
- collect wallet transactions
- return normalized result

This becomes the baseline runner.

## Phase 6 — Extract Sequential Claims Runner

Move `claims`-based logic into:

```text
SequentialClaimsScenarioRunner
```

This runner owns:

- multiple claims against one voucher
- slice/divisible scenarios
- attempt ordering
- repeated claim summaries

This prevents divisible/open/fixed scenarios from polluting the default runner.

## Phase 7 — Extract Settlement Envelope Evaluation Runner

Move `runSettlementEnvelopeScenario()` into:

```text
SettlementEnvelopeEvaluationScenarioRunner
```

Owns:

- readiness payload construction
- readiness evaluation
- checklist comparison
- missing/satisfied expectation checks

This runner should not submit real claims. It evaluates settlement readiness only.

## Phase 8 — Extract Settlement Three-Party Runner

Move `runSettlementThreePartyScenario()` into:

```text
SettlementThreePartyScenarioRunner
```

Owns:

- hospital / patient / payer semantic model
- attestation phase
- settlement phase
- envelope metadata persistence
- attestation-vs-settlement separation
- demo-friendly semantic output

This is the most important extraction because it is the newest and most scenario-specific logic.

## Phase 9 — Convert `lifecycle-scenarios.php` Into Declarative Scenarios

Keep `lifecycle-scenarios.php`, but make the structure more explicitly declarative.

Recommended shape:

```php
'settlement_philhealth_bst_three_party' => [
    'label' => 'Settlement — PhilHealth BST Three-Party Flow',
    'mode' => 'settlement_three_party_flow',

    'actors' => [
        'issuer' => [...],
        'attestor' => [...],
        'payer' => [...],
        'recipient' => [...],
    ],

    'instrument' => [
        'amount' => 20000,
        'currency' => 'PHP',
        'metadata' => [...],
    ],

    'phases' => [
        'issue' => [...],
        'attest' => [...],
        'settle' => [...],
    ],

    'expect' => [...],
]
```

The scenario file should describe what happens.

The runner should decide how to execute it.

## Phase 10 — Final Command Shape

Target command flow:

```php
public function handle(
    LifecycleScenarioRepository $scenarios,
    ScenarioRunnerRegistry $registry,
    LifecycleScenarioResultRenderer $renderer,
): int {
    if ($this->option('list')) {
        return $renderer->renderList($scenarios->all(), $this);
    }

    $scenario = $scenarios->resolve(
        key: (string) $this->argument('scenario'),
        options: $this->options(),
    );

    $context = LifecycleScenarioRunContext::fromCommand(
        scenario: $scenario,
        command: $this,
    );

    $result = $registry
        ->for($scenario->mode)
        ->run($context);

    return $renderer->render($result, $this);
}
```

## Recommended First Slice

Do not start with the hardest extraction.

Start with this:

```text
Slice 1:
Extract LifecycleScenarioResultRenderer
```

Why:

- low-risk
- immediately shrinks the command
- does not alter execution semantics
- makes later runner extraction cleaner
- reduces JSON/human output duplication

Then:

```text
Slice 2:
Extract ScenarioRepository + attempt filtering
```

Then:

```text
Slice 3:
Introduce ScenarioRunnerContract + Registry
```

Then:

```text
Slice 4:
Move settlement_three_party_flow into its own runner
```

## Guiding Rule

The command should answer only three questions:

1. Which scenario did the user ask for?
2. Which runner owns that scenario mode?
3. How should the result be displayed?

Everything else belongs outside the command.

## Final Opinion

Yes, the current command is becoming fragile and bloated.

But that is not a failure. It means the lifecycle demo has become rich enough to reveal the proper architecture.

The next architectural milestone is to promote lifecycle scenarios from “console-command logic” into “mode-specific scenario runners.”

That will make future demos stronger, safer, and easier to extend.
