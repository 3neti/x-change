# Instance Keepsake Export

This document describes how to create and verify a read-only instance keepsake archive for safe migration evidence and post-incident continuity.

## What it is

An instance keepsake is a **one-time, encrypted, non-restorable snapshot** of selected
operator-accessed data for review and audit preparation.

The keepsake is intentionally:

- encrypted
- file-level immutable while the export reference is unchanged
- non-restorable
- provider-safe in its default scope

It is for investigation, portability evidence, and controlled review. It does not
replace banking data migration or provider onboarding.

## Commands

- `x-change:instance-keepsake:keygen` (local)
- `x-change:instance-keepsake:export` (dry-run and create)
- `x-change:instance-keepsake:verify` (off-system verification)

## Recommended flow

1. Generate a local keypair and store the **public key** in
   `XCHANGE_INSTANCE_KEEPSAKE_PUBLIC_KEY`.
2. Run a dry run:

```bash
php artisan x-change:instance-keepsake:export \
  --all-users \
  --confirm-sensitive-export \
  --json
```

3. Copy the `plan_hash` from the dry-run output and create the export:

```bash
php artisan x-change:instance-keepsake:export \
  --all-users \
  --confirm-sensitive-export \
  --create \
  --plan-hash=... \
  --export-reference=ops-keepsake-$(date +%Y%m%d) \
  --authorization-reference=ticket-2026-xxx \
  --download-user=ops@example.test \
  --json
```

4. Download the archive using the returned authenticated link and verify it outside
   the creation service:

```bash
php artisan x-change:instance-keepsake:verify \
  /path/to/downloaded/instance-keepsake.xck \
  --private-key-file=/path/to/private.key \
  --expected-archive-sha256=<ARCHIVE_SHA256> \
  --extract-to=/tmp/keepsake-review \
  --json
```

## Safety rules

- Keep all private key material outside source control and outside Cloud environments.
- Never include precise location JSON sidecars unless `--include-location-data` and
  `--confirm-location-data` are both explicit.
- Do not use keepsakes as a replacement for provider reconciliation, treasury reset,
  or funding state recovery.
- Treat any successful verification as a controlled evidence artifact, not an
  operational restoration permit.

## Command help

Use built-in command help (`--help`) when needed:

- `php artisan x-change:instance-keepsake:keygen --help`
- `php artisan x-change:instance-keepsake:export --help`
- `php artisan x-change:instance-keepsake:verify --help`

This command set is fail-closed by design and reports schema-stable machine output.
