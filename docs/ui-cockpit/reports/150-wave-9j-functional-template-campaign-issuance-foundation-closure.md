# Cockpit Wave 9J — Functional Template/Campaign Issuance Foundation Closure

Status: Complete / Foundation ready
Date: 2026-07-11

## Summary

Wave 9 established the functional foundation for template/campaign-aware Cockpit Pay Code generation without changing the existing money movement or voucher issuance path.

## Completed Foundation

- `CockpitIssuanceDraftData` represents operator/template/campaign issuance intent.
- `CockpitIssuanceCampaignContextData` carries campaign planning context without mutating campaigns.
- `CockpitIssuanceDraftCompilerContract` defines the seam for compiling drafts into a `GeneratePayCodeRequest-compatible payload`.
- `DefaultCockpitIssuanceDraftCompiler` compiles drafts into the existing x-change generation request shape.
- `CockpitIssuanceTemplateRegistryContract` and `DefaultCockpitIssuanceTemplateRegistry` make template keys functional and resolvable.
- `CockpitIssuanceDraftValidatorContract` validates known/enabled templates and minimum amount/currency/count requirements before issuance adoption.
- `CockpitCampaignIssuanceDraftAdapterContract` adapts campaign planning facts into issuance drafts.
- `CockpitIssuanceDraftAuditMetadataBuilderContract` builds safe audit metadata without exposing recipient references, validation secrets, provider payloads, wallet data, or raw payloads.
- A functional characterization proves campaign context can become a valid `GeneratePayCodeRequest-compatible payload` without issuing a Pay Code.

## Boundary Preserved

Wave 9 does not:

- call `GeneratePayCode` from the new draft path;
- change voucher behavior;
- change execution engine behavior;
- mutate campaigns;
- read or debit wallets;
- call providers;
- write journal entries;
- execute actions;
- send feedback;
- move money;
- replace `/x/dashboard`, `/x/pay-codes`, `/x/pay-codes/create`, or `/x/balances`.

## Next recommended wave: Cockpit Wave 10 — Runtime Compiler Adoption

Recommended slices:

1. Wave 10A — Quick Generate Draft Factory from Existing Form Payload
2. Wave 10B — Quick Generate Route Uses Draft Validator and Compiler
3. Wave 10C — Template Profile Defaults Applied During Compilation
4. Wave 10D — Pricing Estimate Preflight for Compiled Drafts
5. Wave 10E — Balance/Funding Preflight using `BuildBalanceOverview`
6. Wave 10F — Campaign Draft Runtime Intake Boundary
7. Wave 10G — Operator-safe Response / Activity Metadata Alignment
8. Wave 10H — Runtime Characterization with Existing GeneratePayCode Path
9. Wave 10I — Manual Local Scenario Verification
10. Wave 10J — Runtime Compiler Adoption Closure
