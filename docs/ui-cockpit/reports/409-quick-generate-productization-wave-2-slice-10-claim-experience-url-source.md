# Quick Generate Productization Wave 2 Slice 10 — Claim Experience URL Source

## Result

The primary Quick Generate result card now labels the generated beneficiary URL source as `Claim experience URL`, `Legacy disburse URL`, or generic `Beneficiary URL`.

## Operator effect

Operators can confirm whether the generated link uses the newer `/x/claim/{code}/experience` claim journey without inspecting route internals.

## Boundary

This is presentation-only. It does not change URL generation, issuance behavior, claim UX behavior, delivery, provider calls, wallet movement, journal writes, action execution, campaign mutation, voucher mutation, or execution behavior.

## Verification

Focused frontend coverage uses a claim-experience URL fixture and verifies the source badge in the primary result card.

