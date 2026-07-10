# Cash/Voucher Upstream BrickMath Fix Execution

Status: Completed upstream

Date: 2026-07-11

## Scope

This checkpoint executed the Wave 5G upstream instructions in the cash and voucher package repositories.

No x-change production behavior, Cockpit UI, host-published assets, routes, controllers, APIs, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior was changed.

## Cash Package Result

Repository:

```text
/Users/rli/PhpstormProjects/packages/cash
```

Commit:

```text
198ff60 cash: normalize monetary floats before BrickMoney
```

Files changed:

```text
src/Models/Cash.php
tests/Unit/Models/CashBrickMathNormalizationTest.php
```

Implemented behavior:

```text
Cash::amount normalizes float monetary input to string before calling Brick\Money\Money::of().
```

Focused red result before fix:

```text
1 failed, 1 passed, 4 assertions
```

Focused green result after fix:

```text
2 passed, 7 assertions
```

Full cash suite:

```text
95 passed, 194 assertions
```

Formatter:

```text
vendor/bin/pint was not installed or executable in the cash package.
```

## Voucher Package Result

Repository:

```text
/Users/rli/PhpstormProjects/packages/voucher
```

Commit:

```text
7a6889e voucher: verify cash persistence avoids BrickMath floats
```

Files changed:

```text
tests/Unit/Actions/GenerateVoucherCashPersistenceBrickMathTest.php
```

Implemented behavior:

```text
No voucher production code change was required.
```

Voucher verification result:

```text
Voucher generation with float cash.amount no longer emits the Brick\Math deprecation after the cash fix.
```

Focused voucher result:

```text
1 passed, 8 assertions
```

Focused voucher execution/regression group:

```text
29 passed, 96 assertions
```

Full voucher suite:

```text
387 passed, 28 skipped, 1159 assertions
```

Formatter:

```text
vendor/bin/pint was not installed or executable in the voucher package.
```

## Boundary Confirmation

This checkpoint did not add:

- x-change production behavior changes;
- Cockpit UI changes;
- committed x-change `.env` changes;
- host-published asset changes;
- route changes;
- controller changes;
- API changes;
- migrations;
- x-change model changes;
- x-change repository changes;
- x-change recorder changes;
- production default durable activity recording;
- new Quick Generate semantics;
- voucher execution behavior changes;
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
- money movement behavior changes.

## Next Recommended Checkpoint

```text
Retry Wave 5H — x-change Characterization Flip
```

Expected x-change transition:

```text
The Brick\Math characterization test should now assert no warning during real GeneratePayCode.
```
