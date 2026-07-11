# Cockpit Wave 33C — Distribution Workspace Route Prop Hydration

## Mission

Hydrate the Distribution Workspace route with a read-only `distribution_workspace_read_model` prop.

## Implemented

`/x/cockpit/pay-codes/{code}/distribution` now receives:

- schema;
- status;
- authorization;
- code;
- sanitized summary;
- share assets;
- channel readiness;
- print template readiness;
- analytics readiness;
- disabled mutation actions;
- redaction metadata.

## Boundary

The route prop is read-only. It does not dispatch feedback, generate QR codes, generate short links, generate print artifacts, mutate vouchers, execute drivers, write journal entries, execute actions, create campaigns, call providers, move money, or expose raw payloads.

## Expected UI Result

No visible UI change is expected until the package Vue page consumes `distribution_workspace_read_model`.

## Next Slice

Cockpit Wave 33D — Distribution Workspace UI Presentation.
