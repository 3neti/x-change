# Deterministic Rider Redirect Compass

Companion: [plan](RIDER_REDIRECT_PLAN.md).

## North star

Every accepted paid redirect instruction has a frozen destination, a durable obligation, a recoverable authorized handoff, and evidence that describes exactly what happened.

## Settled decisions

- Backend selects same-tab navigation after authoritative successful completion.
- No iframe, advance destination prose, or intermediate success countdown.
- Payment timing and financial outcomes remain accurate independently of navigation.
- Prepared redirect, destination receipt, and page initialization are separate evidence.
- External destination cooperation is required for arrival acknowledgment or a destination success palette.
- Hosted landing pages and CDN acceleration are deferred.
- Legacy issued vouchers retain legacy behavior; activation is default-off.

## Current position

Implemented in isolated package worktrees. x-rider local commit `bad38c6` is also preserved on canonical branch `feature/rider-redirect-obligation`; Form Flow local commit `11ae18d` is on `feature/inherited-presentation`. x-change local commit is reported in the final handoff. No pushes, tags, releases or Cloud deployment were performed.

The sandbox uses ignored local Composer manifests and symlinked sources. Its tracked composer.json/composer.lock were not changed. The redirect migration was applied locally, package resources published, npm ci/build completed, and strict asset verification passed. Receipt route and every-minute journal delivery schedule were verified. New-issuance activation remains default-off, including the sandbox; no destination receipt secret was installed.

## Safety invariants

Never execute a payment to retry navigation. Never expose a destination secret, receipt signature, claim evidence, or full sensitive URL in journal payloads. Claim-session authorization is independent of code knowledge. Journal delivery failure does not reverse payment.

## Next gates

Release x-rider with the new ^1.1 API, Form Flow with the ^1.11 terminal response, then x-change. Adopt tagged packages in testing, perform controlled acceptance, and obtain explicit production deployment approval. Local path manifests and symlinks must never be deployed. Hosted landing pages, CDN acceleration and destination palettes remain deferred.


## Acceptance evidence

Total distinct Pest coverage: **390 passed** across the three owning packages. No tests are skipped in these selected runs. The full x-change repository and full host suites were not run.

- Form Flow full Pest suite: 192 passed / 673 assertions (`/private/tmp/rider-form-flow-final.log`).
- x-rider full Pest suite: 94 passed / 328 assertions (`/private/tmp/rider-upstream-final.log`).
- x-change final affected PHP suite: **104 passed / 576 assertions** (`/private/tmp/rider-acceptance-final.log`); covers issuance, claim/session binding, pending/provider failure, journal replay, receipt authentication, compiler ownership, public/cockpit preview and reconciliation.
- Receipt/outbox focused rerun: 2 passed / 13 assertions (`/private/tmp/rider-final-receipt-outbox.log`).
- Frontend: 62 passed across five files (`/private/tmp/rider-final-vue.log`).
- Playwright: Chromium 2 passed and WebKit 2 passed at 390px and 1440px. Synthetic Inertia responses against local host assets proved same-tab navigation, one status reload, no POSTs and no page errors. These are adapter/browser checks, not a real provider or external-site delivery test (`/private/tmp/rider-browser-{chromium,webkit}.log`).
- The fixture-backed basic_cash lifecycle uses an explicitly COMPLETED payout response. The runner's default PENDING fixture correctly fails the handoff eligibility guard and must not be mistaken for confirmed settlement.
- Pint and git diff --check passed in each package. Sandbox production asset build and strict asset doctor passed; route and scheduler inspections passed.
- No actual provider calls, payments, private evidence or destination acknowledgments were made.

## Baseline and release limitations

Two stale assertions were reproduced against baseline source and updated: the attention icon now comes from the semantic icon role; the draft sample is AA317, not PREVIEW. Baseline evidence: `/private/tmp/rider-frontend-baseline.log` and `/private/tmp/rider-storyboard-baseline.log`.

Source composer.json validates, but the historical tracked package lockfile has pre-existing unreleased Form Flow/handler floor warnings and now the new x-rider floor. Tests used the ignored local Composer manifest. Resolve tagged dependencies as part of release; do not promote local paths into a tracked release lockfile.

Recovery requires the same authorized claim session. API/CLI-created claims do not gain browser handoff authority just from code knowledge. Destination receipt keys must be configured before issuance to opt into acknowledgment. Receipts are authenticated destination reports, not proof that a person read the page.

The sandbox already contains unrelated changes from other work and generated publication projections; these were preserved. The shared host source rule was recorded through Boost in x-PayOut `.ai/rules/src.md`; no host implementation was hand-edited.

## Changed package files

### x-change
- `README.md`
- `composer.json`
- `config/rider-redirect.php`
- `database/migrations/2026_10_19_000000_create_x_change_rider_redirect_tables.php`
- `docs/claim-ux/RIDER_REDIRECT_COMPASS.md`
- `docs/claim-ux/RIDER_REDIRECT_PLAN.md`
- `docs/claim-ux/rider-redirect-guide.md`
- `resources/js/cockpit/pages/VoucherDetail.vue`
- `resources/js/cockpit/types.ts`
- `resources/js/pages/x-change/claim/Success.vue`
- `resources/js/pages/x-change/claim/useCompletionStatusPoll.ts`
- `routes/web.php`
- `src/Actions/PayCode/GeneratePayCode.php`
- `src/Actions/Redemption/RecordVoucherClaim.php`
- `src/ClaimWalkthrough/ClaimPreviewArtifactFingerprint.php`
- `src/ClaimWalkthrough/ClaimPreviewScenarioFactory.php`
- `src/Console/Commands/Claim/DeliverRiderRedirectJournalCommand.php`
- `src/Http/Controllers/Web/Claim/ClaimRedirectController.php`
- `src/Http/Controllers/Web/Claim/ClaimStartController.php`
- `src/Http/Controllers/Web/Claim/ClaimSubmitController.php`
- `src/Http/Controllers/Web/Claim/ClaimSuccessPageController.php`
- `src/Http/Controllers/Web/Claim/RiderRedirectReceiptController.php`
- `src/Http/Controllers/Web/Cockpit/CockpitVoucherDetailPageController.php`
- `src/Listeners/HandleConfirmedDisbursement.php`
- `src/Models/RiderRedirect.php`
- `src/Models/RiderRedirectEvent.php`
- `src/Providers/XChangeServiceProvider.php`
- `src/Services/Claim/ClaimExperienceCompiler.php`
- `src/Services/Claim/RiderRedirectEngine.php`
- `src/Services/Claim/RiderRedirectJournal.php`
- `src/Support/Claim/ClaimApprovalOtpResultRedirector.php`
- `src/Support/Claim/CompiledClaimResultRedirector.php`
- `tests/Feature/Claim/RiderRedirectEngineTest.php`
- `tests/Feature/Cockpit/CockpitQuickGenerateClaimPreviewTest.php`
- `tests/browser/rider-redirect-handoff.spec.mjs`
- `tests/frontend/Success.redirect-countdown.test.ts`
- `tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts`

### Form Flow

- `src/Data/TerminalNavigation.php`
- `tests/Unit/TerminalNavigationTest.php`
- `docs/PRESENTATION.md`

### x-rider

- `src/Services/RedirectDestinationPolicy.php`
- `tests/Unit/RedirectDestinationPolicyTest.php`
- `docs/REDIRECTS.md`
- `README.md`

x-journal source is unchanged: the bridge uses its existing immutable recorder API.


## Approved release — 2026-10-20

The issuer approved publishing and shipping to Cloud testing and payout, and explicitly approved allowing any host. Activation will set `X_CHANGE_RIDER_REDIRECT_ENABLED=true` and `X_RIDER_ALLOW_ANY_REDIRECT_HOST=true` in testing first, then payout after testing acceptance. Strict URL validation, session-bound handoff and receipt authentication remain enforced. No destination receipt secret is invented. Legacy vouchers retain their existing execution path; the allowed-host configuration is shared with their legacy resolver.

Release set: Form Flow v1.11.0, OTP v1.4.0, KYC v1.3.0, location v1.4.0, selfie v1.3.0, signature v1.4.0, x-rider v1.1.0, and x-change v1.0.250. Version v1.0.249 is reserved by unrelated local work and is not overwritten or incorporated. The five handler suites were rerun: 100 passed. Combined package acceptance now includes 490 Pest passes from the owning package runs.

Cloud baseline: testing release/v1.0.0-beta.98 at 89519ec6; payout release/v1.0.0-beta.107 at 0ad4985. Clean release worktrees are used; unrelated shared-checkout work is preserved. Both builds already install Composer dependencies, publish verified package resources, run npm ci and build the host; deploy commands migrate and refresh commissioning. Scheduler is enabled. Failed-job baseline is testing 0, payout 2796; payout funding queue size is 7. Historical failed jobs are preserved.

Testing and payout deployment IDs and final smoke evidence will be recorded in a follow-up compass entry after deployment; this package tag records the approved release candidate, not a claim that deployment has already completed.


## Cloud release evidence — 2026-10-20

Published all eight approved tags to GitHub and verified their Packagist source references. Both host lockfiles update only these eight packages and contain no path dependencies. Generated frontend inputs were published through the package command, followed by npm ci and production Vite builds. Focused host checks passed: testing 6 tests / 33 assertions, payout 14 tests / 60 assertions.

- Testing: host d5b582eface08d556170369f7dd933897c9266fa, deployment `depl-a305c459-ba1b-4e1a-93ad-8441e8b96426`, succeeded; required monitor completed in 02:11.
- Payout: host ca42dc07fcaf625ad33ce30475c08ff6d5ae6346, deployment `depl-a305c60a-5a51-4402-8cf6-8cc78075f2a2`, succeeded; required monitor completed in 02:06.

Both live runtimes report the eight expected versions, rider engine enabled and allow-any-host enabled. The rider migration and both tables exist, handoff and receipt routes are registered, strict asset verification passes 862/862 with no stale or missing resources, and journal delivery is scheduled every minute. Payout callback routes remain registered. Public auto-generate and its Claim tab controls render in a mobile Chromium browser with HTTP 200 and no page errors; testing also passed desktop rendering. The initial payout network-idle wait timed out; DOM readiness and visible Claim controls passed after rollout.

Testing queues and failed jobs remain zero. Payout funding queue snapshot is 8; other monitored queues are zero. Failed jobs increased from the earlier baseline 2796 to 2801; one failure occurred after deployment started: VerifyFundingIntentJob on x-change-funding, ModelNotFoundException for PayCodeIssuanceFundingOrder, with no rider reference. Historical failures were preserved; no retries or deletions were performed. This funding failure is an outstanding operational finding, not evidence of a successful funding flow.

No real payments, voucher issuance, provider callbacks or destination arrival receipts were exercised during Cloud smoke checks. Browser checks verify rendering, not an end-to-end paid claim. A prepared handoff remains distinct from destination-confirmed arrival. The published v1.0.250 tag is immutable; this deployment evidence is a documentation-only follow-up.
