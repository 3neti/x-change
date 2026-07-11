# Cockpit Wave 30E — Pay Code Explorer Filter UI Presentation

## Status

Complete.

## Implemented

The Cockpit Pay Code Explorer now renders:

- read-only functional parity stats
- GET-based search field
- GET-based status filter
- active filter summary
- clear filters link

The filter form submits to:

```text
GET /x/cockpit/pay-codes?search={term}&status={status}
```

## Boundary

The UI filter form is navigation only. It does not:

- mutate vouchers
- call providers
- move money
- write journal entries
- execute x-action actions
- send x-feedback deliveries
- mutate campaign state
- expose raw payloads

## Next slice

```text
Cockpit Wave 30F — Pay Code Explorer Filter Browser / Publish Verification
```
