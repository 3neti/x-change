# Retry Wave 5H — x-change Characterization Flip

Status: Completed

Date: 2026-07-11

## Scope

This checkpoint retries Wave 5H after the upstream cash and voucher fixes were applied locally.

The x-change characterization test now asserts that real `GeneratePayCode` issuance does not emit the Brick\Math float deprecation.

No x-change production behavior, Cockpit UI, host-published assets, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior was changed.

## Red/Green Transition

Before updating the x-change test, the old characterization expectation failed:

```text
Expecting [] not to be empty.
```

This proved the upstream fix was active in the local package graph.

Updated test:

```text
it does not emit the brick math float deprecation during voucher cash persistence
```

The test still exercises the real `GeneratePayCode` path and preserves result amount behavior.

## Verification

Focused command:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Actions/GeneratePayCodeIntegrationTest.php --filter=brick
```

Expected result after flip:

```text
1 passed
```

## Upstream Inputs

Cash commit:

```text
198ff60 cash: normalize monetary floats before BrickMoney
```

Voucher commit:

```text
7a6889e voucher: verify cash persistence avoids BrickMath floats
```

## Boundary Confirmation

This checkpoint did not add:

- x-change production behavior changes;
- Cockpit UI changes;
- committed `.env` changes;
- database writes outside test execution;
- database deletes;
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
- money movement behavior changes.

## Next Recommended Checkpoint

```text
Wave 5I — Real Activity Fixture Cleanup Decision / Execution
```

Potential UI effect:

```text
If the synthetic PC-LOCAL-DIAGNOSTIC fixture is removed, its diagnostic card will disappear from the local Cockpit Operator Issuance Activity panel.
```
