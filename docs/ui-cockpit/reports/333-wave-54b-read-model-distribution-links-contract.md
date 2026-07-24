# Cockpit Wave 54B — Read Model Distribution Links Contract

## Status

Completed.

## Scope

Add a read-only `distribution_links` contract to destination read models.

## Backend Contract

Voucher Detail read models now expose:

```text
read_model.voucher.distribution_links
```

Distribution Workspace read models now expose:

```text
distribution_workspace_read_model.distribution_links
```

## Contract Shape

```text
schema
status
available
read_only
redeem_url
redeem_path
source
delivery_enabled
redactions
```

The links are generated from the canonical `x-change.claim.show` route. That route owns the public claim entry and may hand off to the richer experience internally.

## Boundary

The contract is link presentation only.

It does not send feedback, dispatch delivery, generate QR codes, create print artifacts, mutate campaigns, call providers, move money, write journal entries, or execute actions.

## Expected UI Result

No visible UI change yet.

Wave 54C and 54D should render the contract on Pay Code Detail and Distribution Workspace.
