# Cockpit Wave 30D — Pay Code Explorer Controller Query Intake

## Status

Complete.

## Implemented

`CockpitPayCodeExplorerPageController` now accepts read-only query params:

- `search`
- `status`

`CockpitReadOnlyPageProps::toPayCodeExplorerArray()` passes those values into `CockpitReadModelQueryData` as:

- `payCodeSearch`
- `payCodeStatus`

Existing `activity_code` / `activity_source` navigation remains intact.

## Boundary

The query intake is GET-only and read-model-only.

No write route, provider call, voucher mutation, journal write, action execution, feedback delivery, campaign mutation, or money movement was added.

## Next slice

```text
Cockpit Wave 30E — Pay Code Explorer Filter UI Presentation
```
