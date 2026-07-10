# Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip

Status: Blocked pending upstream fix

Date: 2026-07-11

## Scope

This checkpoint attempts the Wave 5H intake gate for the Brick\Math monetary warning.

Wave 5H can only flip the x-change characterization test after the cash and/or voucher package fix has been applied and verified.

This checkpoint does not change x-change production behavior, cash package code, voucher package code, Cockpit UI, local `.env`, database rows, routes, controllers, APIs, migrations, models, repositories, recorders, provider behavior, journal behavior, action behavior, feedback behavior, or money movement.

## Intake Result

Result:

```text
Blocked pending upstream fix.
```

The upstream cash and voucher package working trees were clean, but the warning boundary still appears unchanged.

The x-change characterization test still passes because it still captures the existing warning:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Actions/GeneratePayCodeIntegrationTest.php --filter=brick
```

Result:

```text
1 passed, 6 assertions
```

This means the current local package graph still emits:

```text
Passing floats to BigNumber::of()
```

Therefore, the x-change characterization test must not be flipped yet.

## Upstream Files Inspected

Cash package:

```text
/Users/rli/PhpstormProjects/packages/cash/src/Models/Cash.php
```

Observed boundary:

```text
Money::of($value, $currency)->getMinorAmount()->toInt()
```

The inspected cash mutator still passes `$value` directly into `Money::of()`.

Voucher package:

```text
/Users/rli/PhpstormProjects/packages/voucher/src/Pipelines/Voucher/PersistCash.php
```

Observed boundary:

```text
Cash::create([
    'amount' => $amount,
    'currency' => $currency,
    ...
])
```

The inspected voucher persistence step still passes `$amount` directly into `Cash::create()`.

## Upstream Working Tree Status

Commands:

```bash
git -C /Users/rli/PhpstormProjects/packages/cash status --short
git -C /Users/rli/PhpstormProjects/packages/voucher status --short
```

Observed result:

```text
No output from either repository.
```

Interpretation:

```text
Both upstream package working trees are clean, but no local upstream fix is present at the inspected warning boundary.
```

## Decision

Decision:

```text
Do not flip the x-change characterization test in this checkpoint.
```

Rationale:

- Wave 5F proved the warning is still observable during real `GeneratePayCode`.
- Wave 5G drafted upstream instructions for cash and voucher.
- Wave 5H intake found the upstream fix has not yet been applied locally.
- Flipping the x-change test now would create a false green expectation.

## Required Upstream Work Before Retrying 5H

Cash package:

```text
/Users/rli/PhpstormProjects/packages/cash
```

Expected fix:

```text
Cash::amount set mutator should cast numeric floats to string before calling Money::of().
```

Voucher package:

```text
/Users/rli/PhpstormProjects/packages/voucher
```

Expected verification:

```text
Voucher generation with cash.amount = 25.0 should no longer emit the Brick\Math deprecation after the cash fix.
```

Voucher production code should remain unchanged if the cash package fix fully resolves voucher generation.

Fallback voucher fix, only if still needed:

```text
Voucher PersistCash should pass a decimal string or Money-compatible value into Cash::create().
```

## x-change Return Criteria

Retry Wave 5H only after upstream agents report:

- cash focused monetary tests pass;
- cash full package tests pass, or unrelated failures are documented;
- voucher focused voucher-generation cash persistence tests pass;
- voucher execution-engine/redemption regression tests pass;
- voucher full package tests pass, or unrelated failures are documented;
- upstream commits are available for inspection.

Then x-change should:

1. Update `tests/Feature/Actions/GeneratePayCodeIntegrationTest.php` so the Brick\Math test expects no warning.
2. Keep the same real `GeneratePayCode` path.
3. Preserve result amount, wallet debit, voucher instruction amount, durable activity amount, and redaction behavior.
4. Update this report or add a successor pass report.
5. Update both compasses.

## Boundary Confirmation

This checkpoint did not add:

- x-change production behavior changes;
- cash package edits;
- voucher package edits;
- committed `.env` changes;
- database writes;
- database deletes;
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
Cash/Voucher Upstream BrickMath Fix Execution
```

Recommended action:

Run the Wave 5G instructions in the cash and voucher package repositories before retrying the x-change Wave 5H characterization flip.
