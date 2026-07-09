# Cockpit Mutation Wave 2A — Operator Issuance Activity Read Model Contract

Status: Implemented

## Purpose

Introduce the read-model contract for operator-visible issuance activity before adding any recorder, persistence, journal handoff, action handoff, feedback handoff, or presentation mutation behavior.

This slice defines what Cockpit may safely display as operator issuance activity. It does not create activity records.

## Implemented Contract

Added:

- `CockpitOperatorIssuanceActivityItemData`
- `CockpitOperatorIssuanceActivityReadModelData`
- `CockpitReadModelProviderContract::forOperatorIssuanceActivity()`
- `NullCockpitReadModelProvider::forOperatorIssuanceActivity()`
- `VoucherLifecycleCockpitReadModelProvider::forOperatorIssuanceActivity()`

The default provider returns a safe `not_wired` read model with an empty activity list.

## Operator-safe Facts

The activity item contract permits only summary facts:

- activity ID;
- Pay Code;
- amount;
- currency;
- status;
- issued timestamp;
- source route;
- correlation ID;
- idempotency key;
- operator ID;
- Cockpit detail href;
- safe metadata.

## Redaction Boundary

The read model explicitly records that it does not expose:

- raw payloads;
- provider payloads;
- wallet data;
- recipient secrets;
- lifecycle truth;
- journal writes;
- action execution;
- feedback sending;
- money movement.

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
- provider calls;
- wallet lookup, reservation, debit, or transfer;
- voucher execution changes;
- raw payload persistence;
- automatic retry;
- money movement.

## Tests

Added:

- `tests/Unit/Cockpit/CockpitOperatorIssuanceActivityReadModelTest.php`
- `tests/Unit/Architecture/CockpitOperatorIssuanceActivityReadModelContractTest.php`

The read-model test was red before implementation because the DTOs and provider method did not exist.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivityReadModelTest.php
```

Result: `3 passed, 25 assertions`.

```bash
php -d memory_limit=1G vendor/bin/pest
```

Result: `1096 passed, 5 skipped, 6524 assertions`.

## Next Recommended Slice

```text
Cockpit Mutation Wave 2B — Issuance Activity Recorder Boundary
```

Wave 2B may define a package-local recorder boundary and a null/no-op recorder. It must still avoid persistence, migrations, queues, journal writes, action execution, feedback delivery, provider calls, wallet access, voucher execution changes, and money movement.
