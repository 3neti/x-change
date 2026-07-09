# Cockpit Mutation Wave 2B — Issuance Activity Recorder Boundary

Status: Implemented

## Purpose

Define the package-local boundary for recording operator issuance activity after a successful Cockpit Quick Generate handoff.

This slice adds the recorder seam only. The default implementation is a no-op.

## Implemented Boundary

Added:

- `CockpitOperatorIssuanceActivityRecorderContract`;
- `NullCockpitOperatorIssuanceActivityRecorder`;
- service-provider binding to the null recorder by default;
- Quick Generate mutation handoff to the recorder after fresh issuance.

The controller sends only a `CockpitOperatorIssuanceActivityItemData` DTO to the recorder.

## Recorded Facts

The recorder boundary receives only:

- activity ID;
- Pay Code;
- amount;
- currency;
- status;
- issued timestamp;
- Cockpit route name;
- correlation ID;
- idempotency key;
- operator ID;
- Cockpit detail href;
- safe metadata.

## Replay Behavior

Idempotency replays do not record duplicate operator activity.

Only fresh successful issuance responses are handed to the recorder.

## Failure Behavior

Recorder failures are non-blocking. The existing issuance response remains the operator-facing result.

This preserves the boundary that Cockpit activity recording is operational evidence, not the owner of issuance lifecycle truth.

## Explicit Non-Implementation Statement

This slice does not add:

- persistence;
- migrations;
- queues;
- journal writes;
- x-journal runtime handoff;
- action execution;
- x-action runtime handoff;
- feedback delivery;
- x-feedback runtime handoff;
- campaign mutation;
- provider calls beyond the existing `GeneratePayCode` handoff;
- wallet lookup, reservation, debit, or transfer outside the existing issuance path;
- voucher execution changes;
- raw payload persistence;
- automatic retry;
- money movement outside the existing issuance path.

## Tests

Added:

- `tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRecorderBoundaryTest.php`
- `tests/Unit/Architecture/CockpitOperatorIssuanceActivityRecorderBoundaryTest.php`

The feature test was red before implementation because the recorder contract and binding did not exist.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRecorderBoundaryTest.php
```

Result: `3 passed, 45 assertions`.

```bash
php -d memory_limit=1G vendor/bin/pest
```

Result: `1100 passed, 5 skipped, 6584 assertions`.

## Next Recommended Slice

```text
Cockpit Mutation Wave 2C — Journal Handoff Boundary
```

Wave 2C may define the adapter shape for future x-journal handoff. It must not write journal entries until explicitly authorized.
