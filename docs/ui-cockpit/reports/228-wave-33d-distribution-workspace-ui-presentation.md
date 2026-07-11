# Cockpit Wave 33D — Distribution Workspace UI Presentation

## Mission

Render the hydrated `distribution_workspace_read_model` on the Distribution Workspace page while retaining placeholder fallbacks.

## Implemented

Distribution Workspace now consumes route props and displays hydrated read-only facts for:

- Pay Code;
- distribution status;
- payload policy;
- share assets;
- digital distribution channels;
- print templates;
- operational analytics;
- disabled mutation actions.

The package route adapter now forwards Inertia props into the Cockpit page.

## Boundary

The UI remains read-only. It does not dispatch feedback, generate QR codes, generate short links, generate print artifacts, mutate vouchers, execute drivers, write journal entries, execute actions, create campaigns, call providers, move money, or expose raw payloads.

## Expected UI Result

On `/x/cockpit/pay-codes/{code}/distribution`, operators should see `Distribution Workspace Runtime`, the Pay Code, status, payload policy, hydrated share/channel/print/analytics cards, and blocked action buttons.

## Next Slice

Cockpit Wave 33E — Distribution Workspace Browser / Publish Verification.
