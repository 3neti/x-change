# Cockpit Wave 33B — Distribution Workspace Read Model Contract

## Mission

Add a typed read-model contract for read-only Distribution Workspace share-surface facts before route hydration or UI adoption.

## Added Contract

`CockpitDistributionWorkspaceReadModelData` carries:

- schema;
- status;
- authorization;
- code;
- sanitized summary;
- share assets;
- channels;
- print templates;
- analytics;
- disabled/read-only actions;
- redactions.

`CockpitDistributionWorkspaceItemData` carries each read-only row:

- key;
- label;
- status;
- description;
- read-only flag;
- availability flag;
- source;
- optional href;
- metadata.

## Boundary

The contract is descriptive only. It does not dispatch feedback, generate QR codes, generate short links, generate print artifacts, mutate vouchers, execute drivers, write journal entries, execute actions, create campaigns, call providers, move money, or expose raw payloads.

## Expected UI Result

No visible UI change is expected until route hydration and Vue adoption consume the new read model.

## Next Slice

Cockpit Wave 33C — Distribution Workspace Route Prop Hydration.
