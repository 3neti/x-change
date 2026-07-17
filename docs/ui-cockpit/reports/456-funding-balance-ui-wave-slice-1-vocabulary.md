# Cockpit Funding / Balance UI Wave — Slice 1 — Vocabulary

Date: 2026-07-17

## Outcome

This slice defines the operator-facing vocabulary for Cockpit funding and balance panels before changing the dashboard layout.

The wave remains UI/read-model presentation only. It does not wire wallet Treasury runtime, provider balance refresh, reservation, release, capture, repayment, reversal, or money movement.

## Operator Labels

| Label | Meaning | Source today |
| --- | --- | --- |
| Internal Balance | Current wallet accounting balance visible to Cockpit | x-change Cockpit header read model |
| Outstanding Pay Codes | Active unredeemed Pay Code liability estimate | x-change voucher liability bridge |
| Usable Balance | Internal balance minus outstanding Pay Code estimate | x-change bridge estimate |
| Live Balance | Provider-like balance summary only when explicitly enabled | existing funding overview adapter |

## Required Copy Rules

- Use `estimate` for outstanding and usable values until wallet Treasury has persisted facts.
- Use `not connected` for provider/live balance when no provider summary is enabled.
- Avoid presenting wallet Treasury absent facts as real allocation facts.
- Avoid the term `reserved` in the operator UI until wallet-owned Allocation semantics exist.
- Avoid saying that Cockpit reserves, releases, captures, refunds, or moves money.

## Wallet Treasury Relationship

Wallet Treasury Phase 4/5 is reviewed but not consumed as runtime truth yet.

Cockpit should keep using x-change bridge labels while wallet Treasury reports absent facts:

```text
hasTreasuryFacts = false
treasury_facts = absent
```

Future UI may add Inventory / Allocation / Slice labels only after wallet Treasury has real persisted facts.

## Boundaries Preserved

- No Vue component behavior changed.
- No host-published assets changed.
- No wallet Treasury runtime dependency was added.
- No wallet, voucher, provider, execution, journal, action, feedback, campaign, public API, lifecycle, or money-movement behavior changed.

## Next Slice

Slice 2 should update the dashboard Funding Status panel to use these labels in a scan-friendly layout.
