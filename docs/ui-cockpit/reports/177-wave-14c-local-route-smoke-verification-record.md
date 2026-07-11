# Cockpit Wave 14C — Local Route Smoke Verification Record

## Status

Implemented.

## Purpose

Record that the Cockpit and legacy bridge routes exist locally after Wave 13 and host mirror publish sync.

## Local Base URL

```text
http://x-change-sandbox.test/x/cockpit
```

## Commands

```bash
php artisan route:list --path=x/cockpit --except-vendor
php artisan route:list --path=x/pay-codes --except-vendor
php artisan route:list --path=x/balances --except-vendor
```

## Results

```text
x/cockpit routes: Showing [6] routes
x/pay-codes routes: Showing [4] routes
x/balances routes: Showing [1] routes
```

## Manual Smoke URLs

- `http://x-change-sandbox.test/x/cockpit`
- `http://x-change-sandbox.test/x/cockpit/quick-generate`
- `http://x-change-sandbox.test/x/cockpit/pay-codes`
- `http://x-change-sandbox.test/x/pay-codes/create`
- `http://x-change-sandbox.test/x/pay-codes`
- `http://x-change-sandbox.test/x/balances`

## Expected Visual Result

- Quick Generate primary view shows runtime UI first.
- Historical baseline panels are under diagnostics.
- Legacy pages show Cockpit bridge callouts when bridge props are available.

## Boundary

This checkpoint verifies route availability only. It does not execute browser interactions, issue Pay Codes, mutate wallets, call providers, write journal entries, execute actions, send feedback, or mutate campaigns.

## Next Recommended Checkpoint

Cockpit Wave 14D — Browser Visual Handoff Checklist.
