# Cockpit Mutation Wave 5F — BrickMath Monetary Normalization Characterization

Status: Characterized

Date: 2026-07-11

## Scope

This checkpoint characterizes the Brick\Math float deprecation observed during real Cockpit Quick Generate issuance.

This checkpoint adds test coverage and documentation only. It does not change monetary behavior, voucher behavior, wallet behavior, durable activity behavior, Cockpit UI, local `.env`, database rows, routes, controllers, APIs, migrations, models, repositories, recorders, provider behavior, journal behavior, action behavior, feedback behavior, or money movement.

## Finding

The observed warning is:

```text
Passing floats to BigNumber::of() and arithmetic methods is deprecated and will be removed in 0.15. Cast the float to string explicitly to preserve the previous behaviour.
```

The warning occurs during real voucher cash persistence, not during Cockpit durable activity recording.

## Characterized Call Path

The reproduced call path is:

```text
Cockpit Quick Generate
    ↓
GeneratePayCode
    ↓
PayCodeIssuanceService
    ↓
voucher GeneratesVouchers
    ↓
voucher PersistCash
    ↓
cash Cash::amount mutator
    ↓
Brick\Money\Money::of()
    ↓
Brick\Math\BigNumber::of()
```

Concrete files identified:

```text
/Users/rli/PhpstormProjects/packages/voucher/src/Pipelines/Voucher/PersistCash.php
/Users/rli/PhpstormProjects/packages/cash/src/Models/Cash.php
```

## x-change Boundary Analysis

Candidate x-change files inspected:

```text
packages/x-change/src/Actions/PayCode/GeneratePayCode.php
packages/x-change/src/Actions/PayCode/EstimatePayCodeCost.php
packages/x-change/src/Services/InstructionRevenueAllocatorService.php
packages/x-change/src/Services/VoucherIssuancePayloadNormalizer.php
packages/x-change/src/Services/PayCodeIssuanceService.php
```

Important constraint:

```text
LBHurtado\Voucher\Data\CashInstructionData::$amount is typed as float.
```

Therefore, x-change cannot fully eliminate the deprecation by string-normalizing `cash.amount` before constructing voucher instructions. The voucher DTO currently coerces the value back to `float` before `PersistCash` reaches the cash package.

Changing x-change response or request semantics to force string amounts earlier would risk public API behavior without actually solving the voucher/cash boundary.

## Test Added

Added characterization coverage:

```text
tests/Feature/Actions/GeneratePayCodeIntegrationTest.php
```

Test:

```text
it characterizes the brick math float deprecation during voucher cash persistence
```

The test:

- runs real `GeneratePayCode`;
- captures the known Brick\Math deprecation;
- asserts issuance still succeeds;
- asserts the generated amount remains `25.0`;
- asserts the deprecation trace includes:
  - `/Users/rli/PhpstormProjects/packages/cash/src/Models/Cash.php`;
  - `/Users/rli/PhpstormProjects/packages/voucher/src/Pipelines/Voucher/PersistCash.php`.

Focused result:

```text
php -d memory_limit=1G vendor/bin/pest tests/Feature/Actions/GeneratePayCodeIntegrationTest.php --filter=brick

1 passed, 6 assertions
```

## Decision

Decision:

```text
Do not implement the Brick\Math fix inside x-change in this checkpoint.
```

Rationale:

- The warning originates after x-change hands off to voucher.
- The amount is coerced to `float` by the voucher DTO.
- The final `Money::of()` call happens in the cash package model mutator.
- Fixing this correctly requires coordination with voucher and/or cash package ownership.

## Recommended Fix Direction

Preferred fix boundary:

```text
cash package
```

Recommended cash package fix:

```text
Cash::amount set mutator should cast numeric floats to string before calling Money::of().
```

Alternative or complementary voucher package fix:

```text
Voucher PersistCash should pass a decimal string or Money-compatible value into Cash::create().
```

Potential voucher DTO hardening:

```text
CashInstructionData may need to support string decimal input internally before cash persistence, while preserving public transformation behavior.
```

## Recommended Cross-Package Test Coverage

In `cash`:

- assert `Cash::create(['amount' => 25.0, 'currency' => 'PHP'])` does not emit the Brick\Math deprecation;
- assert stored minor amount is unchanged;
- assert `Money` getter still returns `PHP 25.00`;
- assert string decimal input remains supported.

In `voucher`:

- assert voucher generation with `cash.amount` as float does not emit the Brick\Math deprecation;
- assert persisted `Cash` amount is unchanged;
- assert voucher instructions and redemption behavior remain unchanged.

In `x-change` after upstream fix:

- update the characterization test to expect no Brick\Math deprecation during real `GeneratePayCode`;
- keep response amount, wallet debit, voucher instruction amount, durable activity amount, and redaction behavior unchanged.

## Boundary Confirmation

This checkpoint did not add:

- committed `.env` changes;
- database writes beyond test database execution;
- local database deletes;
- source behavior changes;
- frontend behavior changes;
- host-published asset changes;
- route changes;
- controller changes;
- API changes;
- migrations;
- model changes;
- repository changes;
- recorder changes;
- production default durable activity recording;
- new Quick Generate semantics;
- voucher execution changes;
- journal writes;
- action execution;
- feedback delivery;
- provider calls;
- direct wallet access changes;
- direct wallet mutation changes;
- lifecycle truth ownership;
- raw payload exposure;
- retry controls;
- new mutation controls;
- campaign mutation;
- money movement changes.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination
```

Recommended scope:

- draft exact instructions for the cash and voucher package agents;
- require tests to fail before any upstream production change;
- require full package suites green in cash and voucher;
- after upstream fix, return to x-change and update the characterization test to assert no deprecation.
