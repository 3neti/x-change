# Campaign QR Ph Claim Form Prefill Plan

Last updated: 2026-10-10

## Objective

For a qualifying settled Campaign QR Ph payment from GCash or Maya, suggest the NetBank Sender Name in the editable full-name field and bind the NetBank Source Account as the payer mobile. A QR scan alone provides neither identity. The claimant confirms or corrects the name before redemption.

## Ownership and behavior

- A package-owned `campaign-payment-completion.yaml` copies the voucher redemption flow, including the KYC `$kyc_name` default, and owns the Campaign QR Ph field presentation. x-change selects this driver only for campaign completion and injects eligible payment-specific defaults at claim compilation. Ordinary voucher claims retain `voucher-redemption.yaml`.
- The canonical encrypted payment observation supplies payer name, institution, and account. Only supported GCash/Maya institution codes with a valid Philippine mobile account qualify; missing or invalid evidence leaves the existing manual/OTP flow intact.
- KYC continues to supply the name when present. A suggested payment sender name is never treated as verified identity or written to a Contact before a successful claim.
- After redemption, the confirmed form name fills a blank name on the existing mobile-linked `Contact`. An existing Contact name is preserved. No new model, schema, public API, or financial operation is required.
- Payer identity remains outside Cockpit projections, logs, and public campaign details.

## Acceptance

- Compile both GCash and Maya completions with a locked payer mobile and an editable sender-name suggestion.
- Verify unknown institution, malformed mobile, missing or invalid sender name, and KYC precedence.
- Submit an edited name and verify Contact persistence only after a successful claim. Preserve an existing Contact name and prove replay does not change claim or Contact state.
- Run focused package tests, formatter, and diff checks. Compare wider-suite failures with the known baseline before attributing them to this work.

## Delivery

Implement and commit in `3neti/x-change` first. Host adoption, package push, immutable release, and deployment follow the separate reviewed release protocol.

## Local checkpoint — 2026-10-10

The package implementation is complete locally, including the copied Campaign QR Ph
YAML, driver selection, payment-gated defaults, and confirmed Contact name write.
Focused cases pass for GCash, Maya, KYC precedence, untrusted payer evidence,
name editing, Contact preservation, and failed redemption. The wider claim and
publication suites pass 73 tests / 352 assertions. The full campaign feature
file passes 142 tests / 906 assertions with the same five pre-existing failures
(legacy driver wording and four Cockpit redirects). No host adoption, provider
request, release, or deployment occurred.
