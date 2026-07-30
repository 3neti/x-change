# Provider Failure And Pay Code Recovery Runbook

Date: 2026-07-30

This runbook documents the safe recovery path when a redeemer submits a claim,
the provider accepts the request, and the final provider status later returns as
failed or rejected.

## Current Policy

Do not automatically retry the same payout from the public claim experience.

Provider status can lag, be incomplete, or disagree with dashboard evidence. A
blind same-voucher retry can create duplicate money movement when provider truth
arrives late. Recovery must be operator-led until the provider adapters expose a
provably idempotent retry contract.

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
| Provider dashboard/API says `REJECTED`, with transaction id or status details | `failed` | Review the reason, correct recipient details if needed, then issue a replacement Pay Code. |
| Provider API says failed but lacks transaction evidence | `pending` or `unknown`, `needs_review=true` | Verify provider dashboard before retrying, compensating, or telling the redeemer the payout failed. |
| Provider remains pending/processing | `pending` | Continue polling. Do not issue a replacement unless the provider confirms final failure. |

## Replacement Pay Code Recovery

When the provider has final failed evidence:

1. Record the failed provider transaction id and rejection reason.
2. Confirm the redeemer destination details, especially mobile number, bank
   code, and account number.
3. Issue a new Pay Code for the replacement payout amount.
4. Send the redeemer the new canonical claim URL:

```text
/x/claim/{PAY_CODE}
```

5. Keep the original Pay Code and reconciliation record immutable except for
   reconciliation status updates.

This keeps money movement auditable: the original claim remains the failed
attempt, while the replacement Pay Code becomes the fresh payout attempt.

## Future Retry Contract

A same-voucher retry button can be considered only when all of these exist:

- provider adapter exposes an idempotent retry/replay contract;
- retry request carries the original provider reference and a stable
  idempotency key;
- reconciliation stores each attempt as a separate attempt record or equivalent
  immutable child fact;
- operator UI shows provider evidence, rejection reason, retry amount, and
  destination before execution;
- automated tests prove that `SETTLED` cannot be retried and `REJECTED` creates
  exactly one new provider attempt.

Until then, the safe product story is replacement Pay Code recovery, not
same-voucher payout retry.
