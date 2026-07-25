# x-change Agent Notes

## Live Treasury lifecycle command

When a user asks for the correct syntax for the live sliced Treasury scenario, use this shape:

```bash
XCHANGE_LIFECYCLE_ALLOW_LIVE_PROVIDER_SCENARIOS=true php artisan xchange:lifecycle:run treasury_live_basic_cash --issuer=5 --live-provider --confirm-live-transfer --run-reference=treasury-live-basic-cash-issuer-5-YYYYMMDD-NNN --json
```

Replace the issuer and run-reference suffix only when the requested Account or authorized economic run changes. Do not add `--amount`: this scenario is deliberately fixed at one ₱150 Pay Code claimed through three provider transfers of ₱75, ₱50, and ₱25.

Before suggesting execution, state these controls:

- This is a real-money command. Never execute it automatically or as part of automated acceptance.
- The issuer needs at least ₱165 in provider-specific Client Funds: ₱150 beneficiary principal plus the ₱15 issuance charge.
- Provider liquidity must also support the ₱150 outflow.
- `--run-reference` is mandatory, caller-supplied, and stable for the complete three-slice lifecycle.
- Reuse the exact same reference after a timeout, ambiguous response, pre-issuance affordability failure, or `provider_sync_pending`. Never generate a new reference merely to retry.
- A new reference authorizes a new economic run and can cause three additional transfers.
- The scenario waits ten seconds before the second and third live claims.

Expected success evidence includes:

- `provider_transfers_completed: 3`
- `provider_transfers_expected: 3`
- `accounting_status: reconciled`
- three distinct provider transaction IDs
- Reserve checkpoints of ₱150 → ₱75 → ₱25 → ₱0
- aggregate Provider Inventory outflow of ₱150
- `inventory_equals_positions: true` at every checkpoint

If issuance fails before a Pay Code exists, inspect the reported available, required, and shortfall amounts. No provider transfer occurred. Apply funding only through authoritative provider evidence, then retry the same run reference.

If asked to prove replay protection, explain that rerunning the completed reference should return `replayed: true` and `provider_transfer_repeated: false`. Do not create a new reference for a replay test.
