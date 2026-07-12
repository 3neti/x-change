# Cockpit Wave 41D — Campaign Recipient Source-Link Publish / Browser Verification

## Status

Completed.

## Verification performed

- Published x-change package assets into the host app with `php artisan x-change:install --force`.
- Verified published Cockpit asset drift with `php artisan x-change:doctor --assets --json`.
- Ran the existing campaign source-link Playwright smoke to ensure the Campaign → Quick Generate browser path still works after adding recipient source-link UI.

## Results

- Asset publish: passed.
- Asset drift: passed, `checked: 58`, `ok: 58`, `stale: 0`, `missing: 0`, `extra: 0`.
- Browser smoke: passed, `1 passed`.

## Command notes

The first sandboxed Playwright run failed before browser execution because setup uses `php artisan tinker`, which tried to write PsySH history outside the sandbox. The same command passed with escalated local process/cache access.

## UI coverage

The existing browser smoke verifies the original Campaign `Open Quick Generate` entry point still opens campaign-prefilled Quick Generate. The new recipient-list UI is covered by Vitest in Wave 41C and by the published asset drift guard in this slice.

## Expected UI result

After `php artisan x-change:install --force`, the host-published Cockpit assets include the new `Recipient Quick Generate entry points` section. It appears only when the campaign read model includes safe `recipient_quick_generate_links`.

## Next checkpoint

`Cockpit Wave 41E — Campaign Recipient Source-Link Selection Closure`.
