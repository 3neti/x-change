# Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination

Status: Instruction drafted

Date: 2026-07-11

## Scope

This checkpoint converts the Wave 5F characterization into exact upstream instructions for the cash and voucher package agents.

This checkpoint does not modify cash, voucher, x-change production behavior, Cockpit UI, local `.env`, database rows, routes, controllers, APIs, migrations, models, repositories, recorders, provider behavior, journal behavior, action behavior, feedback behavior, or money movement.

## Prior Finding

Wave 5F characterized this warning during real Cockpit Quick Generate issuance:

```text
Passing floats to BigNumber::of() and arithmetic methods is deprecated and will be removed in 0.15. Cast the float to string explicitly to preserve the previous behaviour.
```

The warning is emitted after x-change hands off to voucher:

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

Important x-change boundary:

```text
LBHurtado\Voucher\Data\CashInstructionData::$amount is typed as float.
```

Because the voucher DTO currently coerces `cash.amount` back to `float`, x-change cannot fully eliminate this warning by string-normalizing the amount before constructing voucher instructions.

## Instruction for the Cash Package Agent

Repository:

```text
/Users/rli/PhpstormProjects/packages/cash
```

Mission:

```text
Fix Brick\Math float deprecation at the cash monetary persistence boundary without changing stored values, public monetary semantics, wallet semantics, or money movement behavior.
```

Expected implementation direction:

```text
Cash::amount set mutator should cast numeric floats to string before calling Money::of().
```

Required test-first workflow:

1. Add a failing test that creates or mutates a `Cash` record with `amount` as `25.0`.
2. Capture PHP warnings/deprecations and prove the current implementation emits `Passing floats to BigNumber::of()`.
3. Implement the smallest production change in the cash package.
4. Prove the warning is no longer emitted.
5. Prove stored minor amount is unchanged.
6. Prove string decimal input still works.
7. Prove the public getter still returns the same monetary value, for example `PHP 25.00`.

Required cash tests:

```text
Cash::create(['amount' => 25.0, 'currency' => 'PHP']) does not emit the Brick\Math deprecation.
Cash::create(['amount' => '25.00', 'currency' => 'PHP']) remains supported.
Stored minor amount for PHP 25.00 remains 2500, or the package's existing equivalent.
The amount getter still returns the package's existing Money/value representation.
Existing wallet/cash behavior remains unchanged.
```

Required cash verification:

```bash
composer validate --strict
vendor/bin/pest <focused cash monetary test>
vendor/bin/pest
```

If `vendor/bin/pint` exists in the package, run:

```bash
vendor/bin/pint --dirty --format agent
```

Stop and report if:

- the fix requires changing public cash APIs;
- the fix changes stored amount precision;
- the fix changes currency handling;
- the fix changes wallet behavior;
- the full cash package suite has unrelated pre-existing failures.

Suggested commit message:

```text
cash: normalize monetary floats before BrickMoney
```

## Instruction for the Voucher Package Agent

Repository:

```text
/Users/rli/PhpstormProjects/packages/voucher
```

Mission:

```text
Verify voucher generation no longer emits the Brick\Math float deprecation after the cash fix, and only add voucher-side normalization if the cash fix is insufficient.
```

Expected implementation direction:

Primary preference:

```text
Do not change voucher production code if the cash package fix eliminates the deprecation for voucher generation.
```

Fallback implementation direction, only if needed:

```text
Voucher PersistCash should pass a decimal string or Money-compatible value into Cash::create().
```

Required test-first workflow:

1. Add a failing or characterization test around voucher generation with `cash.amount` as `25.0`.
2. Capture PHP warnings/deprecations.
3. Prove the warning reaches voucher generation before the upstream fix or before voucher-side normalization.
4. After the cash fix is installed, rerun the test.
5. If the test passes without voucher production changes, keep voucher unchanged and document that the fix belongs to cash.
6. If the warning still appears, implement the smallest voucher-side normalization in `PersistCash`.

Required voucher tests:

```text
Voucher generation with cash.amount = 25.0 does not emit the Brick\Math deprecation.
Persisted Cash amount is unchanged.
Voucher instructions remain unchanged at the public DTO/array boundary.
Redemption behavior remains unchanged.
Legacy vouchers remain compatible.
Execution instruction behavior remains unchanged.
Unknown execution driver fail-closed behavior remains unchanged.
```

Required voucher verification:

```bash
composer validate --strict
vendor/bin/pest <focused voucher cash persistence / generation test>
vendor/bin/pest tests/Unit/Actions/RedeemVoucherTest.php
vendor/bin/pest tests/Unit/Services/ExecutionEngineTest.php tests/Unit/Services/ExecutionDriverRegistryTest.php
vendor/bin/pest
```

If `vendor/bin/pint` exists in the package, run:

```bash
vendor/bin/pint --dirty --format agent
```

Stop and report if:

- the fix requires changing voucher public DTO semantics;
- the fix changes voucher instruction payloads;
- the fix changes voucher generation behavior;
- the fix changes redemption behavior;
- the fix changes execution engine behavior;
- the fix changes money movement behavior;
- the full voucher package suite has unrelated pre-existing failures.

Suggested commit message:

```text
voucher: verify cash persistence avoids BrickMath floats
```

If voucher production code is required:

```text
voucher: normalize cash persistence amount for BrickMath
```

## Return Instruction for x-change

After the cash and voucher package agents complete their work, return to:

```text
/Users/rli/PhpstormProjects/x-change-sandbox/packages/x-change
```

Required x-change follow-up:

1. Update the Wave 5F characterization test so it expects no `Passing floats to BigNumber::of()` warning during real `GeneratePayCode`.
2. Keep the same real issuance path through `GeneratePayCode`.
3. Preserve result amount, wallet debit, voucher instruction amount, durable activity amount, and redaction assertions.
4. Do not change Quick Generate request/response semantics unless a separate explicit Cockpit slice authorizes it.
5. Update the Cockpit Compass and Settlement OS Compass with the upstream fix result.

Required x-change verification:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Actions/GeneratePayCodeIntegrationTest.php --filter=brick
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitBrickMathMonetaryNormalizationCharacterizationTest.php
```

Expected x-change test transition:

```text
Before upstream fix:
The characterization test expects the warning.

After upstream fix:
The characterization test must assert the warning is absent.
```

Suggested x-change follow-up commit message:

```text
cockpit: verify brickmath monetary warning is resolved
```

## Boundary Confirmation

This checkpoint does not authorize:

- x-change production behavior changes;
- cash package edits from inside x-change;
- voucher package edits from inside x-change;
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

## Coordination Status

Current status:

```text
Instruction ready for cash and voucher package agents.
```

Do not flip the x-change characterization test until upstream package fixes are applied and verified.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip
```

Recommended scope:

- inspect the cash and voucher package reports/commits;
- verify their focused and full test results;
- update x-change characterization coverage to assert no warning;
- preserve Quick Generate, wallet, voucher, durable activity, and redaction behavior;
- update both compasses.
