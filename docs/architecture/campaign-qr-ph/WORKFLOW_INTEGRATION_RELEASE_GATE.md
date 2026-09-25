# Workflow integration release and local host adoption

Date: 2026-09-25. Gate 3c complete; Cloud deployment excluded.

## Published releases

| Package | Version | Commit |
| --- | --- | --- |
| [settlement-envelope](https://github.com/3neti/settlement-envelope/tree/v1.3.0) | v1.3.0 | a1b4576b7b8e89c8962154aabbc884f743c37af5 |
| [settlement-envelope-aui](https://github.com/3neti/settlement-envelope-aui/tree/v1.0.0) | v1.0.0 | 449e5c7cc15bd9059788d8d569730a1cf6fc445d |
| [settlement-envelope-philhealth](https://github.com/3neti/settlement-envelope-philhealth/tree/v1.0.0) | v1.0.0 | dd6796e2b46efff6d6a7966efc4ff88d6c8eec96 |
| [x-change](https://github.com/3neti/x-change/tree/v1.0.50) | v1.0.50 | e923478df4fba28d8b65b71a24f5ffc894dad1d6 |

Each main/tag pair was pushed atomically in dependency order. No existing tag was
moved. The user created the two new repositories and their Packagist entries;
registry source references were verified. Temporary VCS entries were removed
before x-change publication. Normal Composer resolution now works through Packagist.

The x-change lock changes only envelope v1.2.0 → v1.3.0 and adds the two v1.0.0
integrations. No unrelated runtime dependency updates or local path repositories.

## Local host

Host: `/Users/rli/PhpstormProjects/x-change-sandbox`.
Upgraded x-change v1.0.45 → v1.0.50 with `^1.0.50` minimum and immutable lock.
Both integrations and envelope match the release table above.

Composer install ran with scripts disabled to keep overwrite-capable publication
under explicit control. Then package discovery and
`x-change:publish --scope=build --force --verify --no-interaction` ran normally,
followed by asset doctor and Vite build. No configuration cache, broad installer,
migration, financial doctor, commissioning or provider preflight was invoked.

The pre-existing Campaigns and CampaignPolicyLifecycle host differences were exact
v1.0.48 generated package projections, not independent host customization. Other
overlapping campaign files matched package source. A private local backup of these
generated inputs exists at `/tmp/xchange-v1050-host-preservation/generated-before.tar`.
Unrelated `.env.example`, Pipedream, settings, deployment skill and other host
changes were preserved and excluded from the adoption commit.

Host adoption files:

- `composer.json`, `composer.lock`.
- `config/envelope-drivers/aui.personal-accident.provisional-cover.yaml`.
- Generated campaign UI: `resources/js/cockpit/pages/Campaigns.vue`,
  `CampaignPolicyLifecycle.vue`, `LeadCampaignLifecycleScenarioRun.vue`,
  `resources/js/cockpit/components/CockpitCampaignPaymentQrStamp.vue`,
  `resources/js/cockpit/campaignPolicyLifecycle.ts`.
- `tests/Feature/WorkflowIntegrationAdoptionTest.php` checks stable compatible
  versions, installed resources, no symlinks, default-deny discovery and no HTTP.

Compiled `public/build` and generated Wayfinder outputs follow existing host ignore
rules; no ignored artifacts were force-added. The host adoption commit stays local.

## Verification

| Check | Result |
| --- | --- |
| Envelope full PHP suite | 177 passed / 543 assertions; four existing skips |
| AUI standalone against published dependencies | 86 passed / 127 assertions |
| PhilHealth standalone against published dependencies | 23 passed / 54 assertions |
| x-change six affected PHP suites against release dependencies | 171 passed / 1,133 assertions |
| x-change campaign lifecycle + lead runner frontend | 20 passed / two files |
| Host adoption + transport configuration | 3 passed / 23 assertions |
| Host build publication | All 15 declared resources verified |
| Host strict asset doctor | Passed; generated inputs match package source |
| Host production build | Passed; 3,708 modules, manifest emitted |
| Strict Composer validation, Pint, diff whitespace checks | Passed |

Non-blocking warnings: existing abandoned `eloquent/enumeration`; third-party
VueUse PURE annotation positions, chunk size above 500 kB and plugin timings.
Composer reported no security vulnerability advisories during lock updates.
These checks do not claim full x-change/host-suite or financial lifecycle coverage.

## Preserved boundaries and next work

Parser/transport delegation preserves x-change's accepted-disposition and durable
execution authority. Installation does not activate catalog workflows or grant
reviewer/payout authority. BST remains synthetic, local/testing-only and pending
review. Insurer calls, payments, claims and SMS were not performed.

No browser acceptance or browser lifecycle was performed in this release gate;
no browser control tool was available. The requested host upgrade is local only:
testing Cloud and x-PayOut Cloud are unchanged.

Next product gate: template-backed draft editor integration, with the documented
validation/reviewer-authority boundaries retained. AUI and PhilHealth browser
lifecycle extensions follow their separate plan gates. No live authority is
inferred from these releases.
