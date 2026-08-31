# Campaign Payroll Recovery Lifecycle

This runbook covers the controlled payroll scenario where a checker approves a batch, X-Change attempts direct provider payouts, and any trusted provider rejection falls back to the same protected Pay Code through the ordinary `/x/claim/{code}` experience.

## Operating Model

The direct-transfer scenario is intentionally built on Pay Codes. Approval creates one Pay Code per beneficiary. A successful provider payout auto-claims and settles that Pay Code. A trusted provider rejection does not create a second recovery token; it opens recovery on the same Pay Code and sends the beneficiary a normal claim link.

The recovery message must use:

```text
/x/claim/{code}
```

It must not use legacy `/payout-recovery/...` URLs.

## CSV Shapes

Direct transfer input:

```csv
name,mobile,bank,account number,amount
Apple Hurtado,09175180722,BDO,00066159231,25.00
Lester Hurtado,09173011987,BDO,000661592316,30.00
```

Pay Code distribution input:

```csv
name,mobile,amount
Apple Hurtado,09175180722,25.00
Lester Hurtado,09173011987,30.00
```

## Live Run

Prepare the frozen worksheet and approval Pay Code:

```bash
XCHANGE_LIFECYCLE_ALLOW_LIVE_PROVIDER_SCENARIOS=true php artisan xchange:lifecycle:run campaign_payroll_direct_transfer \
  --provider=netbank \
  --maker=3 \
  --checker=1 \
  --input=/path/to/payroll.csv \
  --phase=prepare \
  --live-provider \
  --live-feedback \
  --confirm-live-transfer \
  --run-reference=PAYROLL-BDO-RECOVERY-YYYYMMDD-001 \
  --json \
  --no-interaction
```

Approve and execute the direct-transfer leg:

```bash
XCHANGE_LIFECYCLE_ALLOW_LIVE_PROVIDER_SCENARIOS=true php artisan xchange:lifecycle:run campaign_payroll_direct_transfer \
  --provider=netbank \
  --maker=3 \
  --checker=1 \
  --phase=approve \
  --live-provider \
  --live-feedback \
  --confirm-live-transfer \
  --confirm-checker-approval \
  --run-reference=PAYROLL-BDO-RECOVERY-YYYYMMDD-001 \
  --json \
  --no-interaction
```

If the result is `recovery_waiting_feedback_gate`, queue recovery notifications:

```bash
XCHANGE_LIFECYCLE_ALLOW_LIVE_PROVIDER_SCENARIOS=true php artisan xchange:lifecycle:run campaign_payroll_direct_transfer \
  --provider=netbank \
  --maker=3 \
  --checker=1 \
  --phase=fallback \
  --live-provider \
  --live-feedback \
  --confirm-live-transfer \
  --run-reference=PAYROLL-BDO-RECOVERY-YYYYMMDD-001 \
  --json \
  --no-interaction
```

## Required Workers

Recovery SMS is queued on `x-change-feedback`. A default-only worker will not send it.

Run a dedicated feedback worker in production:

```bash
php artisan queue:work database --queue=x-change-feedback --sleep=3 --timeout=60
```

For local all-lane development:

```bash
php artisan queue:work database --queue=x-change-funding,x-change-feedback,default --sleep=3 --timeout=60
```

Do not blindly drain all queues during a live recovery investigation. Inspect the queued jobs first and process only the intended campaign recovery job if there are unrelated notifications waiting.

## Inspection

Show recovery delivery attempts:

```bash
php artisan x-change:campaigns:payout-recovery-deliveries \
  --authorization=AUTHORIZATION_REFERENCE \
  --json
```

Show a specific recovery Pay Code:

```bash
php artisan x-change:campaigns:payout-recovery-deliveries \
  --pay-code=CAMP-XXXX \
  --json
```

The expected recovery event chain is:

```text
requested -> queued -> provider_queued -> completed
```

If a beneficiary already claimed before the SMS job ran, the final event should be:

```text
superseded
```

with safe error code:

```text
campaign_payout_recovery_no_longer_claimable
```

## Exact Job Recovery

When there are mixed jobs on `x-change-feedback`, use the exact-job command instead of a broad worker drain.

```bash
php artisan x-change:campaigns:payout-recovery-deliveries \
  --authorization=AUTHORIZATION_REFERENCE \
  --pay-code=CAMP-XXXX \
  --process-job=143 \
  --confirm-send \
  --json
```

The command validates that the queued job belongs to the requested campaign payout recovery before firing it. `--confirm-send` is required because the job may contact the SMS provider.

## State Invariants

- A trusted provider rejection opens recovery on the same Pay Code.
- Recovery notifications are sent only while the rejected claim remains claimable.
- A stale recovery job must record `superseded` and must not contact the SMS provider.
- The recovery message uses the ordinary claim UX and instruction-driven OTP.
- Provider-indeterminate outcomes do not trigger recovery.
- Recovery delivery state is audit evidence, not monetary truth.

## Local Verification

Focused test commands:

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/Campaigns/CampaignPayoutRecoveryFallbackTest.php
php -d memory_limit=2G vendor/bin/pest tests/Feature/Console/CampaignBatchLifecycleScenarioTest.php
```

Format and diff checks:

```bash
vendor/bin/pint --dirty --format agent
git diff --check
```
