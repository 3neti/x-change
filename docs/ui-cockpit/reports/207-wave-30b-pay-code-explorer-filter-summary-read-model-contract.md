# Cockpit Wave 30B — Pay Code Explorer Filter / Summary Read Model Contract

## Status

Complete.

## Added contract fields

`CockpitPayCodeListReadModelData` now carries operator-safe parity metadata:

- `query`
- `status_filter`
- `stats`
- `filters`
- `records`
- `redactions`

New DTOs:

- `CockpitPayCodeExplorerStatsData`
- `CockpitPayCodeExplorerFilterData`

## Boundary

This slice only extends the read model contract. It does not:

- change legacy `/x/pay-codes`
- register new mutation routes
- mutate vouchers
- execute drivers
- call providers
- move money
- write journal entries
- execute x-action actions
- send x-feedback deliveries
- expose raw payloads

## Next slice

```text
Cockpit Wave 30C — Pay Code Explorer Provider Filtering and Stats Parity
```
