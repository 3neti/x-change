# Cockpit Wave 29A — Pay Code Explorer Runtime Parity / Activity Navigation Bridge Audit

## Status

Complete.

## Objective

Audit the current Cockpit Pay Code Explorer against the existing `/x/pay-codes` surface and define the first read-only runtime bridge from Operator Issuance Activity.

## Current Cockpit Explorer

Current route:

```text
GET /x/cockpit/pay-codes
```

Current component:

```text
packages/x-change/resources/js/cockpit/pages/PayCodeExplorer.vue
```

Current read model:

```text
CockpitPayCodeListReadModelData
CockpitPayCodeListRecordData
CockpitReadModelProviderContract::forPayCodeList()
```

Current behavior:

- renders sanitized Pay Code list rows;
- renders read-only integration badges;
- renders campaign navigation context when supplied;
- does not mutate vouchers;
- does not execute drivers;
- does not approve claims;
- does not send feedback;
- does not write journal entries;
- does not call providers;
- does not move money.

## Existing `/x/pay-codes` Surface

The legacy page has richer functional behavior:

```text
resources/js/pages/x-change/pay-codes/Index.vue
```

Observed capabilities include:

- status-oriented list filtering;
- stats cards;
- list table;
- detail and approval navigation;
- create-page navigation;
- direct legacy page behavior.

## First Bridge Decision

Implement a read-only activity navigation bridge before broader Explorer parity work.

Accepted bridge shape:

```text
Operator Issuance Activity card
    ↓
Open in Explorer
    ↓
/x/cockpit/pay-codes?activity_code={code}&activity_source=operator_issuance_activity
    ↓
Cockpit Pay Code Explorer
```

## Explicit Boundaries

Wave 29A does not add:

- Pay Code mutation;
- redemption;
- approval;
- provider calls;
- wallet mutation;
- voucher execution mutation;
- journal/action/feedback write expansion;
- campaign mutation;
- saved filters;
- raw payload display.

## Next Checkpoint

Cockpit Wave 29B — Pay Code Explorer Activity Query Intake.
