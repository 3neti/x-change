# Provider-Aware Balance Authority

## Target Model

x-change must not use one universal wallet balance rule for every provider topology.

- NetBank / `ledger_pooled`: the local Bavix wallet is the user balance ledger.
- Paynamics / `provider_customer_wallet`: the Paynamics customer wallet is the user's public digital wallet and authoritative spendable balance.
- Manual: the local Bavix wallet remains authoritative.

The default safety rule is fail closed: if the selected balance authority cannot be proven, Pay Code issuance and claim settlement must not continue.

## Wallet Roles

- Local ledger wallet: Bavix wallet used by x-change for local accounting, reservation, fees, and NetBank-style pooled ledger flows.
- Provider wallet: Paynamics customer wallet, externally funded by the user through Paynamics-supported channels such as bank transfer.
- System wallet: platform-local wallet owner used as the transfer counterparty for NetBank/manual local-ledger funding.
- Provider account: operator credentials or provider runtime identity, such as NetBank source/CASA configuration or Paynamics merchant credentials.

## Funding Rules

For `ledger_pooled`, x-change checks the issuer's local Bavix wallet. Funding/top-up should use the wallet package action:

```text
system/platform wallet -> issuer/user local wallet
```

For `provider_customer_wallet`, x-change checks the provider wallet projection refreshed from Paynamics. Local Bavix balance must not block issuance merely because it is zero.

```text
Paynamics customer wallet -> authoritative spendable balance
x-change local ledger     -> audit/reservation/accounting projection
```

For `manual`, x-change continues to use the local wallet balance.

## Implementation Notes

- `ProviderAwareFundingPolicy` decides the authoritative balance source.
- `GeneratePayCode` calls the funding policy before voucher creation.
- Local-ledger authorities still allocate instruction revenue through local wallet transfers.
- Provider-wallet authorities skip mandatory local revenue transfer in this slice, because the user's spendable funds are external to Bavix.
- Paynamics balance is read from the EMI wallet projection after provider refresh.
- Missing Paynamics provider wallet link, failed refresh, missing projection, or insufficient provider balance blocks issuance.
- A registered user may link an existing Paynamics wallet ID from profile/settings. This proves the wallet exists by syncing balance, then stores an x-change provider account link and EMI wallet projection.
- TODO: Ideally confirm ownership using mobile number, email, name, KYC reference, OTP, or another Paynamics-supported challenge before upgrading `unverified_manual_link` to owner-verified.

## Boundaries

x-change production code should not directly deposit into user wallets. User funding must go through approved wallet package actions or provider synchronization/import flows.

Direct withdrawals should stay behind approved wallet/settlement boundaries until they are replaced with package-owned actions.

## Tests

- Local-ledger funding allows issuance when Bavix balance is sufficient.
- Local-ledger funding blocks when Bavix balance is insufficient.
- Paynamics funding allows issuance when local Bavix balance is zero but provider wallet balance is sufficient.
- Paynamics funding blocks when provider refresh fails.
- Architecture tests prevent direct Bavix deposits in x-change production code.
