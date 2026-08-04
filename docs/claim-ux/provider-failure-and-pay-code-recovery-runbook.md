# Provider Failure And Pay Code Recovery Runbook

Date: 2026-08-04

This runbook documents the safe recovery path when a redeemer submits a claim,
the provider accepts the request, and the final provider status later returns as
failed or rejected.

## Current Policy

Do not automatically retry the same payout from the public claim experience.

Provider status can lag, be incomplete, or disagree with dashboard evidence. A
blind retry can create duplicate money movement when provider truth arrives
late. x-change therefore permits same-Pay-Code correction only after an
authoritative final rejection has been reconciled. The claim stays immutable,
the corrected destination becomes a new encrypted revision, and the provider
receives a new attempt reference under the same Pay Code.

The accounting transition is:

```text
Claim accepted, payout pending       Pay Code Reserve remains reserved
Provider authoritatively rejects     Pay Code Reserve → Beneficiary Payout Payable
Corrected payout succeeds            Beneficiary Payout Payable derecognized
                                     Provider Inventory decreases
Controlled cancellation              Beneficiary Payout Payable → Client Funds
                                     Provider Inventory unchanged
```

A provider rejection never rolls back or deletes the confirmed claim, and it
does not automatically return principal to the issuer.

## Diagnostic Entry Point

Use the status-check command first:

```bash
php artisan xchange:disbursement:check {PAY_CODE} --json
```

Use `--sync` only when the operator intends to persist the freshly fetched
provider status into the reconciliation record:

```bash
php artisan xchange:disbursement:check {PAY_CODE} --sync --json
```

The payload includes:

- `resolved_status`
- `needs_review`
- `rejection_reason`
- `status_details[].message`
- `operator_guidance.action`
- `operator_guidance.message`

## Operator Decision Matrix

| Provider evidence | Local result | Operator action |
| --- | --- | --- |
| Provider dashboard says `SETTLED` or equivalent final success | `succeeded` | No replacement Pay Code. Confirm the redeemer received funds. |
| Provider dashboard/API says `REJECTED`, with transaction id or status details | `failed` | Reconcile the final rejection, then correct the payout destination under the same Pay Code. |
| Provider API says failed but lacks transaction evidence | `pending` or `unknown`, `needs_review=true` | Verify provider dashboard before retrying, compensating, or telling the redeemer the payout failed. |
| Provider remains pending/processing | `pending` | Continue polling. Do not issue a replacement unless the provider confirms final failure. |

## Same-Pay-Code Refurbishment

When the provider has final failed evidence:

1. Record the failed provider transaction id and rejection reason.
2. In Pay Code Detail, open **Claim & Evidence**. The page must say **Claim
   recorded · Payout rejected** and show the sanitized provider reason.
3. Enter the corrected institution and destination. The original destination
   is never prefilled or exposed.
4. x-change validates the institution, supported rail, and account format
   before a provider call. GCash and Maya wallet destinations must be 11-digit
   Philippine mobile accounts beginning with `09`.
5. If the provider adapter offers account inquiry, its result may add an
   authoritative validation result. NetBank currently has no beneficiary
   account-inquiry capability in x-change, so the UI explicitly says that
   format checks passed but the receiving institution makes the final decision.
6. Submit the correction. x-change persists an encrypted, immutable destination
   revision and `pay_code.payout_destination.revised` journal entry before the
   provider call. The new reconciliation uses `{PAY_CODE}-R{version}` while the
   rejected reconciliation remains intact.
7. Continue normal reconciliation for `PENDING`. On `SUCCEEDED`, Treasury
   derecognizes the Beneficiary Payout Payable and provider Inventory. On a new
   final rejection, the same Pay Code may receive another versioned correction.

The correction is owner-authorized, throttled, and guarded by a non-overlapping
lock. It cannot change the amount, currency, original claim, or issuer charges.

## Validation Limits

Local validation can reject malformed destinations before money is submitted.
It cannot prove that an otherwise well-formed account exists, is active, or
belongs to the intended person. Never describe local validation as bank
verification. A provider-side account validation is authoritative only when a
provider package explicitly contributes that capability.
