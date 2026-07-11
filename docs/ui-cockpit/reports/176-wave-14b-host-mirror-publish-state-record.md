# Cockpit Wave 14B — Host Mirror Publish State Record

## Status

Implemented.

## Purpose

Record the host-published Cockpit mirror files that now match Wave 13 package source.

## Host-Published Mirrors

The following host files are generated/published mirrors of package Cockpit assets:

- `resources/js/cockpit/components/CockpitDiagnosticsDisclosure.vue`
- `resources/js/cockpit/components/CockpitGenerateActionPanel.vue`
- `resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue`
- `resources/js/cockpit/pages/QuickGenerate.vue`
- `resources/js/cockpit/quickGenerateDefaults.ts`
- `resources/js/cockpit/types.ts`

## Source of Truth

The package source remains authoritative:

```text
packages/x-change/resources/js/cockpit
```

The committed host-published mirrors exist so the local host app can run the Cockpit UI without stale generated assets.

## Boundary

This checkpoint does not change package source or runtime behavior. It records the synchronized host-published mirror state for local UI verification.

## Next Recommended Checkpoint

Cockpit Wave 14C — Local Route Smoke Verification Record.
