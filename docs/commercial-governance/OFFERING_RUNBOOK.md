# Commercial Offering Runbook

## Inspect

Use the protected commissioning checklist or:

```bash
php artisan x-change:commercial:governance-status --json
```

Confirm both governed profiles are active and the displayed snapshot hashes match the intended release evidence.

## Revise

1. Sign in as the named maker.
2. Open **Commercial Controls**.
3. Select the profile.
4. Edit the price list or waterfall.
5. Submit the new immutable version.

Submission does not change live pricing.

## Approve and publish

1. Sign in as a different named checker.
2. Inspect the version, effective time, prices, waterfall, legal trace, and hash.
3. Enter the institutional authorization reference.
4. Select **Approve & Publish**.

Publication records independent approval but leaves the currently active version untouched.

## Activate

1. Confirm the published version is intended for the current deployment window.
2. Enter a stable activation or deployment reference.
3. Select **Activate Version**.
4. Re-run governance status and issue a small non-live acceptance Pay Code.

Activation retires the prior active version for future resolution. It does not rewrite prior Vouchers, Commercial Sales, allocations, Treasury postings, or journal entries.

## Recover

- Hash mismatch: stop. Compare package/release provenance and stored snapshot evidence. Never edit the hash to make the check pass.
- Published but inactive: either activate deliberately or leave the existing version active.
- Missing role separation: authorize different named people; do not proxy them through the System Principal.
- Missing activation: governed issuance fails closed. Restore the expected activation from authoritative evidence rather than enabling a silent configuration fallback.
