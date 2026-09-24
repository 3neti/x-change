# Campaign QR Ph Compass

Last updated: 2026-09-25

## North Star

A reusable campaign QR Ph can accept many provider payments. Each qualifying
settled payment is recognized exactly once, binds authoritative provisional
coverage, creates a first-class settlement envelope containing that coverage
snapshot from inception, and issues one zero-value completion Pay Code.

## Current Position

### Payment activity local host acceptance — 2026-09-25

Commit `5c4cc459` passed isolated host adoption from sandbox commit `3873d386`.
The disposable host `/private/tmp/x-change-campaign-activity-gate` used a temporary
Composer path override, not a released version. Build publication verified all
15 resources; strict asset doctor passed; production Vite build passed (3,705
modules). The host policy transport configuration test passed (1 / 5 assertions).
Build warnings about third-party pure annotations and chunk size were non-fatal.

Real Chromium acceptance against intercepted synthetic Inertia responses and the
production-built assets passed at 1440, 375, and 320px. Each width had matching
document clientWidth/scrollWidth, no JavaScript errors, the expected 7 payments /
6 submitted / 6 demo summaries / 1 awaiting claim, the PHP 50 premium, and a
generated campaign-filtered activity href. This is built-UI acceptance, not a
live authenticated lifecycle run. No server, provider, SMS, or financial action
was invoked. The first fixture used the old Inertia page attribute; switching
to Inertia v3's JSON script element resolved fixture loading without source edits.

Screenshots and the isolated browser spec are retained under the sandbox's
untracked `output/campaign-payment-progress-gate/` directory. No existing sandbox
source, Composer files, or environment credentials were changed. No push, tag,
release, or deployment occurred. Next: explicit publication authorization, then
tagged host adoption and testing-instance verification against persisted records.

### Entry-mode-aware campaign activity — 2026-09-24 (local)

The Endpoint Campaign row previously used only `usage_count` and display sessions.
Printed payment-first QR Ph journeys do not enter that path, so seven recognized
payments and six completed demo journeys could appear as zero activity.

The local correction adds `CampaignPaymentProgressReadModel`: five grouped reads
over recognition-backed journeys, scoped to the owner's selected payment-first
campaigns, with no full history hydration. It reports recognized payment count,
gross amounts separately by currency, completed claim-evidence projections,
successful demo outcomes, pending claims, and payments awaiting invitation
issuance. Retries do not create extra counts. Demo success requires successful
request and outcome statuses plus `policy_issued_demo`; it is not real insurance.

Campaign rows now lead with Show Payment QR Ph and View Activity, show the bound
fixed premium (or open-amount wording), and distinguish the secondary public-link
QR. View Activity filters the existing lifecycle page by an owner-checked campaign
reference before its bounded list limit. Public-link progress remains unchanged.
Quarantine remains a separate attention badge and is not recognized revenue.

Owning files: `src/Services/Cockpit/CampaignPaymentProgressReadModel.php`,
`CampaignPolicyLifecycleReadModel.php` in the same directory;
`src/Http/Controllers/Web/Cockpit/CockpitCampaignWorksheetController.php` and
`CockpitCampaignPolicyLifecyclePageController.php`; campaign and policy-lifecycle
Vue pages; their focused frontend tests/route stub; and
`tests/Feature/Actions/Campaigns/BindCampaignPaymentQrTest.php`.

Verification: affected backend suites 146 passed / 1,132 assertions; final focused
regressions (including two additional unsuccessful demo-code cases) 7 passed /
53 assertions; full Cockpit frontend suite 556 passed across 67 files. No provider
requests, live claims, financial mutations, dependencies, schema, `usage_count`,
limits, journal semantics, or pause behavior changed. The unrelated dirty sandbox
is untouched. No host asset publication/build, browser acceptance, release, or
Cloud deployment is included in this local gate. Next gate: release/adopt in an
isolated host, build, then verify the testing rows against current persisted data.
Provider-side behavior when pausing an already printed QR Ph remains a separate
safety investigation. SMS delivery detail and expanded quarantine drill-down are
not part of this slice.

### Private demo-summary applicant details — 2026-09-24

The user approved showing submitted name, address, birth date, mobile, and email
on the expiring signed demo summary and publishing/deploying to testing. The page
reads only those five scalar fields from the existing policy preparation's
persisted claim-evidence projection. Missing/non-string values are not guessed.
No provider payer-name fallback, payment-account fields, OTP proof, raw metadata,
artifact paths, or identity-verification assertion is exposed. The public-safe
summary and SMS/transport/outcome payloads remain unchanged.

Signed-link validation and expiry, private no-store/no-referrer/noindex headers,
and escaped Vue rendering remain; Inertia history encryption is now enabled for
this response. A valid link is a bearer credential, not authenticated identity:
anyone holding it can view the fields, including recipients of older unexpired
links. The page warns not to share it. Demonstration-only/not-insurance notice is
retained. No payment or claim processing is changed; duplicate-submission work
remains deferred.

Verification: full affected campaign suite 96 passed / 678 assertions; Vue summary
tests 2 passed (including escaped applicant text and missing values); Pint and
whitespace checks passed. Independent privacy review found no blockers. Testing
publication/deployment is authorized for this slice; no live SMS or claim replay.

### Wallet-bound completion without OTP — 2026-09-24 (local)

The user confirmed the v1.0.46 live lifecycle worked, then approved removing OTP
from this demonstration completion journey. New completion instructions from the
exact AUI demo driver omit OTP only when the canonical payment observation resolves
to a supported GCash/Maya Philippine mobile. Unknown institutions and malformed or
missing payer accounts keep OTP. Existing issuance snapshots remain authoritative
on retries; no old voucher is rewritten or downgraded. Ordinary claims, onboarding,
and explicitly requested OTP instructions are unchanged.

The prefilled readonly mobile and server-side normalized payer binding remain.
This is an SMS-link-based lower-assurance flow, not OTP verification or independent
KYC. A forwarded link can be used to submit details for the bound mobile. The
voucher engine's existing 12-hour expiry from issuance remains unchanged (distinct
from the 24-hour provisional coverage period). The default execution driver still
rejects expired and already-redeemed vouchers. No financial behavior changes.

Regression coverage exercises compiled forms without OTP, real completion claim
submission and policy evidence without fabricated OTP data, GCash/Maya formats,
unknown/malformed payer fallback, historical OTP issuance retry, mobile tampering,
expiry rejection, and second-redemption rejection. Both complete affected suites
(`BindCampaignPaymentQrTest.php`, `ClaimWorkflowTest.php`) passed: 110 tests /
796 assertions, 155.95 seconds. Pint and whitespace checks passed. Independent
review found no blockers. No browser integration was performed for this local
slice; compiled-form and submission behavior were exercised in package tests.
No live SMS/payment, host adoption, or deployment occurred.

Immediate follow-up: a duplicate direct `SubmitCompiledFormClaim` invocation was
observed to hit a `voucher_claims` uniqueness error while preparing evidence. This
slice verifies execution-level second-redemption rejection, not graceful duplicate
browser submission. Investigate and add a dedicated regression in a separate gate;
do not weaken uniqueness or replay controls.

### v1.0.46 testing release acceptance — 2026-09-24

Authorized package commit `b7b5b555` was published as `v1.0.46`. Host commit
`57b47a60` deployed successfully in testing via
`depl-a2d26d62-024c-4450-89b1-93038931cc03`. Runtime source/version and strict asset
doctor passed; the public claim page rendered in the in-app browser. Local package
checks passed 103 tests / 753 assertions; host focused checks passed 3 tests /
7 assertions, publication verified 15 resources, and production build passed.
The unrelated host boundary test's literal-version/caret-constraint mismatch was
reproduced on the preceding release. Unrelated local host edits were preserved.
The user subsequently confirmed a fresh paid lifecycle worked. No new timing
measurement was independently collected; the 30-second target is not established.

### Local release-candidate gate — 2026-09-24

The accumulated completion workflow, payer-mobile binding/tamper guard, per-step
copy polish, and read-only payment-to-SMS timing diagnostics passed the complete
affected suites together: `BindCampaignPaymentQrTest.php` and
`ClaimWorkflowTest.php`, 103 tests / 753 assertions (159.21 seconds). Composer
strict validation, Pint, and `git diff --check` passed. Independent review found
no blocking issue. This is not a claim that the entire package suite was run.

Browser acceptance is the synthetic, nonfinancial local inspection recorded
below, not a fresh live payment. No frontend source/dependencies/migrations change;
no new frontend build was needed. Host v1.0.45 remains restored with committed
Composer files unchanged and unrelated host edits preserved.

Prepare this as a local commit on main. Proposed next release is v1.0.46, subject
to checking remote tag availability at publication. Push/tag and testing-host
adoption/deployment need explicit authorization; none occurred in this gate.
Do not change polling or promise the 30-second target as part of this release.
OTP-proof hardening remains a separate security follow-up.

### Completion form copy polish — 2026-09-24 (local, unreleased)

The exact `campaign.coverage-completion.v1` workflow now supplies a distinct
Payment received summary and Continue action to intermediate generic Form Flow
steps. The main Complete Your Details heading is retained. Mobile no longer uses
the Redeemer field group. Top-level workflow metadata remains Review your details
/ Submit Details for final confirmation; OTP and other handlers are unchanged.
This uses existing per-step configuration, with no upstream contract or frontend
component change and no change to mobile binding, evidence, or money movement.

Verification: ClaimWorkflowTest passed 15 tests / 134 assertions; the real compiled
completion regression passed 1 test / 22 assertions. Pint passed. A temporary local
Composer link and synthetic session-only preview verified the revised first form
in the in-app browser (1280x720), then Continue advanced to OTP. No code was sent.
The preview used YAML fields and the real resolver/mutator with synthetic mobile
binding, not an issued voucher or paid lifecycle. The temporary host route and
fixture were removed, and the locked v1.0.45 package restored. No build was needed
because no frontend source changed. No push/tag/release/deployment.

Next: prepare the accumulated local completion UX changes and diagnostics for a
reviewed release gate. Stronger OTP proof validation and SMS detection latency are
still separate outstanding work; this copy change does not claim to solve them.

### Local rendered completion-form acceptance — 2026-09-24

Temporarily adopted the unpublished package via the documented alternate Composer
path workflow. The dry run and install changed only x-change. A temporary,
local-host-only fixture constructed wallet/bio fields from the package YAML and
applied the real completion workflow resolver/mutator with a synthetic bound
mobile. It created only Form Flow session state, not a voucher, recognition,
coverage record, or financial entry. This is rendered-form acceptance, not a new
end-to-end paid campaign run or proof of payment-to-mobile resolution.

In-app browser evidence:
- Complete Your Details displayed the prefilled, read-only synthetic mobile.
- No amount, rail, bank, or account-number inputs appeared.
- Submitting the mobile step without entering payout details reached Personal
  Information (name, email, birth date, address).
- Synthetic personal details advanced to OTP; the same mobile appeared read-only.
- Stopped before Send Verification Code; no OTP or policy SMS was requested.
- At 375px, document clientWidth/scrollWidth were both 375; OTP was also inspected
  at 1440px with a centered card and action. No browser console errors reported.

Observed polish follow-up: the mobile group still says Redeemer, the introductory
title repeats, and Submit Details appears on intermediate steps. Refine these
through the shared Form Flow UI contract, scoped to this workflow, before claiming
the complete onboarding-style polish is finished. OTP evidence hardening and SMS
latency remain separate outstanding gates.

The temporary route/file and alternate Composer files were removed. Composer
restored the non-symlink v1.0.45 installation; committed Composer files and routes
were unchanged. No frontend files were published or rebuilt (no frontend source
changed). The preview tab was closed and viewport reset; uncompleted synthetic
session data expires normally. No push, tag, release, or Cloud deployment.

### Completion submission integration gate — 2026-09-24 (local, unreleased)

Expanded the real `SubmitCompiledFormClaim` regression to cover GCash national
and Maya international payer-mobile formats, plus the existing unbound case.
The test no longer supplies even empty bank/account fields. It executes the real
claim pipeline and completion driver, verifies redemption and the persisted mobile
instruction, projects only declared evidence, checks projection replay, and proves
financial record counts remain unchanged with no outgoing HTTP requests. OTP is
fixture evidence here, not a live OTP delivery/proof-verification test.

The three integration cases passed 45 assertions. Browser acceptance was not
performed: the sandbox still installs released v1.0.45, not this working tree.
No Composer override, host publication, live claim, payment, SMS, or deployment
was performed. Next gate: temporary documented local package adoption with an
isolated completion fixture and browser inspection, then restore the host's locked
dependency state. Do not report browser acceptance from these PHP tests.

### Completion claim workflow gate — 2026-09-24 (local, unreleased)

Added an exact `campaign_coverage_completion` workflow: Complete Your Details,
Review your details, Submit Details. It uses the existing claimant handoff and
Form Flow, removes amount/rail/bank/account fields, and does not create an account
or assign onboarding roles. Ordinary payout and onboarding branches are unchanged.

New completion vouchers carry the resolved GCash/Maya payer mobile in the existing
`cash.validation.mobile` instruction. The workflow independently resolves the
mobile from the persisted issuance -> coverage -> recognition -> observation
chain, including for older completion vouchers without that instruction. Mobile
is read-only and prefilled, with local persistence disabled so another user's
remembered number cannot replace it. Form validation permits equivalent 09/639/
+639 formats only; the execution driver rechecks normalized contact mobile against
the authoritative payer. No mobile is placed in a query string. Unsupported
source institutions retain explicit mobile collection; no arbitrary bank account
is inferred to be a mobile number.

The mobile field remains in collected Form Flow data for the existing OTP handler;
the claimant does not type it. OTP requirements and provider calls are unchanged.
This slice does not upgrade the generic OTP evidence model: independent review
found completion evidence accepts OTP booleans, unlike onboarding's proof-shape/
purpose/mobile/TTL checks. Record that as a separate security hardening gate before
claiming onboarding-equivalent identity assurance; payer binding alone is not KYC.

No host/vendor edits, live messages, provider calls, deployment, or dependency
changes. Browser acceptance remains for host adoption; package tests compile the
actual YAML and exercise the real Form Handler validation with fake providers.

Validation: the combined campaign/claim run passed all 86 campaign tests and 12
claim tests, with one existing claim test blocked by its unpublished Testbench
YAML assumption. That test now explicitly loads the authoritative package YAML;
the complete claim file rerun passed 13 tests / 114 assertions. Pint (host binary)
and whitespace checks passed. The source is uncommitted alongside the prior
local diagnostics slice; no Composer path override or generated host changes
were introduced. The scoped form and execution tests cover wrong-mobile rejection
and equivalent national/international mobile formats.

### Payment-to-SMS diagnostics gate — 2026-09-24 (local, unreleased)

Extended the existing read-only default of `x-change:campaigns:resume-payment`
with settlement, observation, recognition, issuance, SMS queue/submission and
handset-delivery timestamps, source and webhook-presence indicators, and stage
durations. No payload, mobile, credential, or signed URL is included. Queued
and failed attempts are not presented as successful SMS submissions; missing
or reversed timestamps produce null durations. `--dispatch` remains an explicit
separate mutation, unchanged by the report.

Testing runtime read-only inspection confirmed for the new `POLI-QPTW` payment:
settled 08:48:26 UTC, observed/recognized 08:50:02 UTC, source
`netbank-vca-transaction-history`, no linked webhook receipt. Scheduled standing
sync is enabled with a 60-second minimum interval. Claim completed 08:52:21 UTC.
Previously verified first-SMS provider submission was 08:50:04 UTC: 96 seconds
to detection, two more to submission. These timestamps cannot separate past
scheduler wait, queue wait and provider latency.

Code-level hypothesis: the once-per-minute scheduler applies the 60-second
eligibility window to `last_checked_at`, which is saved after provider work.
A check completing at :02 misses the next minute's :00 tick, potentially making
effective polling nearly two minutes. Runtime interval matches this hypothesis;
per-job timing is still needed before attributing all delay to it.

Webhook readiness: the existing NetBank route is
`POST /api/x/v1/funding/webhooks/netbank`. It authenticates via exact source-IP
allowlist and `text/plain`, persists receipts, and queues authoritative provider
verification; it does not trust an incoming notification as payment truth.
Pipedream forwarding requires a reviewed authenticated relay contract; do not
allow broad shared IP ranges or bypass content/authentication checks. The legacy
Pipedream destination was not inspected or changed in this gate.

Claim UX assessment: `envelope_completion` currently has no dedicated branch in
`DefaultClaimWorkflowResolver`, so it falls back to disbursement requirements.
Next separate correction: recognize the exact completion driver, suppress payout
fields, bind the mobile from authoritative payment evidence, preserve OTP and
server-side tamper rejection, and reuse Form Flow without account creation.
No claim UX or polling behavior was changed in this diagnostics gate. No Cloud
configuration, provider requests, financial postings or new SMS were triggered.

Operator inspection (omit `--dispatch`):

```bash
php artisan x-change:campaigns:resume-payment <recognition-reference>
```

Validation: full `BindCampaignPaymentQrTest.php` passed 81 tests / 548
assertions; post-format focused diagnostics and standing-sync checks passed
7 tests / 38 assertions. Pint and `git diff --check` passed. Package Pint was
absent, so the installed host Pint binary formatted package files. An initial
restricted test run could not write Testbench logs; the permitted rerun passed.
No frontend change, build or browser acceptance was needed for this console-only
slice. Source remains local and uncommitted; no push, tag, host Composer update,
or deployment was performed. Existing unrelated host changes were preserved.

### Testing activation and existing-claim continuation — 2026-09-24

The user explicitly authorized copying the existing Pipedream completion token
and endpoint into Laravel Cloud secrets for testing only, activating campaign
`01M390MEHA17TMETD5NZFQ5Z28`, and sending the follow-up for `POLI-W23C`.
Both values were securely attached only to the testing environment; no values
were exposed or rotated. This supersedes the activation stop recorded below.

Deployment `depl-a2d24128-1afe-4dc4-8f44-b02bdb69b1ff` succeeded, retaining
x-change `v1.0.45` and host `9941c05a`. Runtime checks confirmed transport,
single-campaign automation, summary and SMS activation. The existing projection
`01M396Z7AMXSFZ9JY6GXC5X95P` was queued once after guarding claim 145,
voucher `POLI-W23C`, its existing redemption, and absence of a prior request.
No payment, claim, or human approval was replayed or fabricated.

The normal worker completed request `01M399DQWFN0H4WPGBTEFDKX17` and outcome
`01M399DVBYANDDMCFTCVDE28W3` successfully with result `policy_issued_demo`.
Feedback delivery record 20 reports `sent`, provider status `ACCEPTED`, with
one matching delivery record and last attempt at 2026-09-24 08:45:03 UTC.
Handset receipt remains for the user to confirm; `sent` is not proof of handset
delivery.

The signed SMS destination was verified in the in-app browser: demo reference
`AUI-DEMO-923FBFB062E8B01D`, demonstration period September 24–25, 2026 at
2:19 PM PHT, prominent DEMONSTRATION ONLY notice, no applicant/payment-account
details or Cockpit shell. The signed bearer URL is intentionally not persisted
here. This is not an issued insurance policy or proof of coverage. The full
fresh-payment-to-SMS 30-second target remains unmeasured; this run resumed an
older completed claim. x-PayOut and other campaigns were not activated.

### v1.0.45 testing adoption — 2026-09-24

Published package commit `4a843626` as `v1.0.45`. Host commit `9941c05a`
adopts that version and adds testing-host Pipedream disposition configuration
(`config/campaign-policy.php`, `AppServiceProvider`, focused configuration
test). This is host-specific endpoint/credential/acceptance wiring, not copied
business logic. Other transport entries are preserved; environment reads stay
in configuration files. The credential reference uses the approved
`services.pipedream.policy_completion_token` namespace. Schema digests derive
from the installed package contracts. No endpoint or secret value is committed.

Host test passed (1 test / 5 assertions); formatting, isolated package
publication/assets doctor, production frontend build, and whitespace checks
passed. Testing deployment `depl-a2d23df9-5d89-4103-a2e3-1e908bb726cd`
succeeded and runtime verified `v1.0.45`. Unrelated sandbox edits were excluded.
x-PayOut was not changed.

**Activation stop:** the approval system rejected copying the existing local
Pipedream token into Cloud secrets without explicit authorization naming that
credential and destination. No workaround was attempted. Neither endpoint nor
token was copied; all transport/automation/summary/SMS flags remain false.
`POLI-W23C` still has its recovered projection and zero policy requests.
No second SMS was sent. Obtain authorization to copy the existing
`PIPEDREAM_AUI_POLICY_COMPLETION_TOKEN` and
`PIPEDREAM_AUI_POLICY_COMPLETION_ENDPOINT` from the sandbox `.env` into secrets
attached only to testing environment `env-a26631ca-18ab-47b2-b676-cc986aea1a69`.
Then activate only campaign `01M390MEHA17TMETD5NZFQ5Z28`, deploy configuration,
verify readiness, and enqueue exact projection `01M396Z7AMXSFZ9JY6GXC5X95P`.
No new payment, claim, maker/checker identity, or credential rotation is needed.

### Automatic post-claim demonstration completion — 2026-09-24

The user explicitly replaced the per-claim maker/checker requirement for this
campaign journey: pay the printed Campaign QR Ph, receive the completion SMS,
claim through the existing Form Flow, then receive a policy link. The earlier
request to designate actors is superseded for this opted-in demonstration path.
Manual policy-completion actions retain their existing authorization checks.

Implemented locally: `CompletionClaimEvidenceProjected` dispatches a unique,
after-commit `CompleteAutomaticDemonstrationPolicyJob`. It rechecks an explicit
campaign allowlist and the exact reserved demo driver/version, prepares the
existing verified evidence chain, and persists an automatically authorized
request. The existing campaign owner is attribution only; the durable
`authorization_mode=automatic_demo` and `automatic-demo:{projection-reference}`
identify the system action. No human checker, approval reference, or approval
timestamp is fabricated. Existing manual requests are never converted.

The existing accepted Pipedream test transport is reused, including its limited
payload, credentials, stable idempotency key, and strict demo response checks.
HTTP runs outside database transactions. Outcome persistence reuses fingerprint
validation, encryption, safe-field validation, immutable hashes, uniqueness,
and the existing outcome event. That event uses the same journaled follow-up
SMS and signed summary page as the manual path. A persisted outcome is returned
before any repeat HTTP request. Worker uniqueness/overlap locks complement DB
uniqueness; they do not promise exactly-once external delivery after a crash.

Activation requires:

- `XCHANGE_AUTOMATIC_DEMO_POLICY_ENABLED=true`
- `XCHANGE_AUTOMATIC_DEMO_POLICY_CAMPAIGNS=<exact campaign reference(s)>`
- the accepted Pipedream test disposition and credential
- the existing demo-summary and demo-summary-SMS flags enabled
- a continuously running worker consuming the configured feedback queue

Defaults remain disabled and the campaign list empty. Disabling automation
stops jobs before they start the transport; it does not recall in-flight HTTP
or an already queued SMS. The signed page remains available according to its
own feature flag and expiry, independently of the automation switch.

The existing `POLI-W23C` projection can be explicitly enqueued by its reference
after release and activation. Do not pay again, redeem again, or flush a whole
queue. Verify one automatic request/outcome, one feedback correlation, provider
acceptance, and the signed summary in a browser. This local implementation has
not yet sent a live second SMS or been released/deployed.

Thirty seconds is a performance target, not a demonstrated SLA. Measure payment
settlement/observation, first SMS enqueue/provider acceptance, claim completion,
insurer response, and second SMS enqueue/provider acceptance separately. Human
data-entry time, bank confirmation, polling cadence, and carrier delivery are
not controlled by this job. An actual insurance policy still requires a real
accepted insurer integration: Pipedream returns `document_ready=false`, and the
current link is explicitly a demonstration summary, never proof of coverage.

Local verification: campaign payment/coverage/completion, lifecycle runner,
and Pipedream suites passed **88 tests / 779 assertions**. The six new automatic
flow tests cover no-checker success, one HTTP/outcome/SMS delivery on replay,
disabled/unlisted campaigns, after-commit dispatch, worker rechecks, provider
failure recovery, and preservation of manual requests. All external calls
were mocked. No Cloud configuration or live records changed in this slice.

Files in this slice (relative to the package):

- `src/Services/Settlement/AutomaticDemonstrationPolicy.php`
- `src/Actions/Settlement/CompleteAutomaticDemonstrationPolicy.php`
- `src/Actions/Settlement/RecordCampaignPolicyCompletionOutcome.php`
- `src/Services/Settlement/DemonstrationPolicySummary.php`
- `src/Jobs/Campaigns/CompleteAutomaticDemonstrationPolicyJob.php`
- `src/Listeners/QueueAutomaticDemonstrationPolicy.php`
- `src/Providers/XChangeServiceProvider.php`
- `config/x-change.php`
- `tests/Feature/Actions/Campaigns/BindCampaignPaymentQrTest.php`
- This compass.

### Testing release and exact-claim recovery — 2026-09-24

Published `v1.0.44` at package commit `9075f6b2`. Sandbox adoption commit
`1cfa5d06` changed only its Composer requirement and lock. Testing deployment
`depl-a2d23202-b436-45ef-a218-20e1b6c51afa` succeeded. Runtime reports
`v1.0.44`, and the built manifest contains `DemonstrationPolicySummary.vue`.
x-PayOut was not changed. Both demonstration-summary activation flags remain
false; no policy authority was granted and no second SMS was sent.

Recovered only `POLI-W23C` (voucher 334, completed claim 145) through
`ProjectCompletionClaimEvidence::handle()`. Projection
`01M396Z7AMXSFZ9JY6GXC5X95P` contains the six declared fields: address,
birth_date, email, mobile, name, and otp. All 15 source evidence records remain.
An immediate replay returned `created=false`, with one projection and unchanged
redemption time. Read-only policy preparation succeeded with six evidence
fields. No policy request exists. No payment, redemption, insurer transport,
or financial posting was replayed.

**Next controlled gate:** explicitly designate the testing policy-completion
maker, independent checker, and outcome recorder. All three configured ID lists
were empty. Do not invent actors or substitute an operator's identity. Then
authorize the governed demonstration request/approval/transport/outcome flow
and the two feature flags, verify the exact outcome's journaled second SMS,
and inspect its signed demonstration summary in the browser. This is not a
real insurer policy. No additional payment or completion claim is required.

Host verification: isolated package publication/assets doctor and production
build passed; the new page is in the manifest. Host checks reported six passes
and two baseline failures: the migration test hardcodes EMI `v2.0.0-beta.5`
although the unchanged lock uses `v2.0.2`; the boundary test rejects the existing
untracked local `packages/x-change` directory. These were not rewritten or
removed. Unrelated host edits were excluded from the release. End-to-end
second-SMS/browser acceptance remains pending the authority gate above.

### Demonstration summary and follow-up SMS — local gate, 2026-09-24

This supersedes the earlier statement that no follow-up presentation exists.
The package now provides a **demonstration policy summary**, not an insurer
policy document. Pipedream's v1 response contract remains unchanged with
`document_ready=false`. No insurer availability or real coverage is inferred.

The continuation is:

1. Completed claim projects declared evidence into the envelope.
2. Existing maker request, independent checker approval, and authorized
   recorder persist a successful `policy_issued_demo` outcome.
3. `PolicyCompletionOutcomeRecorded` queues summary notification after commit.
4. The worker reloads and validates the outcome, then uses the existing
   journaled feedback/SMS pipeline and the original GCash/Maya payer mobile.
5. The SMS opens `/x/demo/policies/{outcome-reference}` through a temporary
   signed URL. The scenario runner's outcome artifact points to the same page
   when enabled. The link is bearer access: recipients should not share it.

New package configuration (all host activation remains a separate gate):

| Environment key | Default | Meaning |
| --- | --- | --- |
| `XCHANGE_DEMO_POLICY_SUMMARY_ENABLED` | `false` | Allow the redacted demo page/link |
| `XCHANGE_DEMO_POLICY_SUMMARY_SMS_ENABLED` | `false` | Queue the follow-up SMS after eligible outcomes |
| `XCHANGE_DEMO_POLICY_SUMMARY_LINK_TTL_HOURS` | `168` | Link lifetime from outcome recording; clamped to 1–720 hours |

Both enable flags must be true for automatic SMS preparation. The page is
read-only, signed, throttled, no-store, no-referrer, and noindex. It shows only
the demo reference, product, authoritative demo dates, recording time, and
explicit non-insurance disclaimer. No applicant answers, contact information,
bank details, claim tokens, or insurer private result are returned. The page
has no Cockpit shell or additional data-entry workflow.

Job identity is `campaign-demo-policy:{outcome-reference}`. Unique dispatch,
overlap protection, and the existing feedback delivery key prevent ordinary
replay from creating a second delivery record. This is not an exactly-once
provider-send guarantee: retain the existing SMS provider ambiguity/runbook
controls. The link expiry does not extend on retries. Disabling the flags
prevents new summary preparation; it does not recall a generic encrypted SMS
delivery job already queued. Inspect exact delivery jobs rather than flushing
the entire queue. SMS failure must never rewrite the policy outcome.

Release acceptance remains pending: publish/adopt the package and built page,
recover `POLI-W23C` claim 145's projection, and complete the governed demo
request/approval/outcome stages with authorized actors. Enable the two flags
only with explicit host/SMS approval. If an eligible outcome predates listener
activation, dispatch `SendDemonstrationPolicySummaryJob` for that exact outcome
reference; do not rerun payment, claim, insurer transport, or outcome issuance.
Inspect its `campaign-demo-policy:` correlation and obtain the link privately.
No automatic maker/checker approval is introduced. Testing Cloud and x-PayOut
are unchanged by this local gate.

Verification: related Campaign QR/lifecycle runner/Pipedream suites passed
82 tests / 742 assertions; shared journaled delivery passed 7 tests / 47
assertions. Strengthened guest-access and duplicate-event checks passed on
focused reruns (21 and 10 assertions). The new Vue test passed (1 test).
Pint and diff whitespace validation passed. No browser acceptance, host
publication, production build, live transport, release, or deployment was
performed; those remain the host-adoption gate.

Implementation inventory (all paths relative to the x-change package):

- `src/Services/Settlement/DemonstrationPolicySummary.php`: eligibility, expiry, signed link, redacted presentation.
- `src/Services/Settlement/CampaignWalletPayerMobile.php`: shared existing wallet-mobile rule.
- `src/Actions/Campaigns/SendCampaignPaymentCompletionSms.php`: reuse that shared rule without changing the first SMS.
- `src/Actions/Campaigns/SendDemonstrationPolicySummarySms.php`: journaled, default-off follow-up SMS.
- `src/Jobs/Campaigns/SendDemonstrationPolicySummaryJob.php`: unique, bounded-retry notification orchestration.
- `src/Listeners/QueueDemonstrationPolicySummary.php`: successful-outcome listener.
- `src/Providers/XChangeServiceProvider.php`: package listener registration.
- `src/Http/Controllers/Web/Claim/DemonstrationPolicySummaryController.php`: read-only public presentation and privacy headers.
- `routes/web.php`: signed, throttled route.
- `config/x-change.php`: activation flags and bounded lifetime.
- `resources/js/pages/x-change/claim/DemonstrationPolicySummary.vue`: public claim-shell summary.
- `src/Services/Leads/LeadCampaignLifecycleScenarioRunReadModel.php`: link the existing outcome artifact.
- `tests/Feature/Actions/Campaigns/BindCampaignPaymentQrTest.php`: security, replay, rollback, delivery, and disabled-state coverage.
- `tests/Feature/Leads/LeadCampaignScenarioRunnerTest.php`: gated artifact link coverage.
- `tests/frontend/DemonstrationPolicySummary.test.ts`: explicit demo wording and PHT presentation.
- This compass: activation, recovery, limitations, and acceptance record.

### Completion evidence corrective slice — 2026-09-24

The user confirmed receipt of the initial SMS and completed `POLI-W23C`.
Read-only testing diagnostics found claim 145 redeemed at 07:20:36 UTC,
with finalized persisted evidence, but no completion evidence projection or
policy request. The browser persisted ancillary Form Flow fields alongside
the six declared requirements. Exact equality against all evidence keys
rejected projection after redemption had already been recorded.

The local correction requires every declared field, ignores ancillary fields
for projection, and retains the complete original claim evidence. Manifest,
private source references, event count, and insurer preparation now use only
the selected evidence. Missing or duplicate declared evidence still fails
closed; replay remains hash-checked and creates no duplicate envelope version.

Release acceptance must recover the existing claim through
`ProjectCompletionClaimEvidence::handle()` using its exact persisted identity,
verify one projection and the declared-field manifest, then prepare the existing
policy workflow. Do not redeem again, request another payment, or reissue the
completion Pay Code. This slice does not send a second SMS automatically.
Publication, testing deployment, and exact-claim recovery remain pending.

The demonstration insurer contract still explicitly returns
`document_ready=false`; there is no downloadable policy URL or policy-link SMS
implementation. A separate controlled gate must define that demonstration
document and notification contract, retaining existing maker/checker authority.
Do not present the fake policy result as actual insurer coverage.

Local verification: Campaign QR and Pipedream transport suites passed 62 tests
/ 442 assertions; compiled-claim and lifecycle-runner suites passed 9 tests
/ 237 assertions. Pint and `git diff --check` passed. All external transports
were mocked; no Cloud record, payment, or SMS was changed by this correction.

Gate 9b recognition bridge is implemented locally. A provider-verified
settlement Pay Code collection can now become an idempotent campaign payment
recognition through an explicit immutable payment source. This path performs no
second collection, wallet credit, Treasury posting, or provider call. The
lifecycle runner exposes the recognition and intentionally pauses before
provisional coverage and completion Pay Code issuance.

Gate 9c is now implemented locally. Exact AUI demonstration runs advance from
the recognition into immutable provisional coverage, a versioned settlement
envelope, and one zero-value completion Pay Code. The operational checkpoint is
`awaiting_completion_claim`; neither a claimed completion nor a live insurer
submission is inferred.

Current gate: **Gate 4 complete — qualifying payment recognition operational**

Current state: **Compatible qualifying payments are recognized exactly once;
nonqualifying or unsafe evidence is durably quarantined**

Prior checkpoint: **Reusable multi-payment characterization complete**

The existing payment-purpose standing-address path is now explicitly
characterized and regression-protected. It persists immutable provider
observations and classifies them without creating Account Funding receipts,
Client Funds credits, or Treasury operations. Repeated synchronization is
idempotent at the provider observation boundary.

At the characterization checkpoint, the installed standing-address persistence
did not retain the optional provider-neutral payer identity DTO. EMI Core
`v2.0.1` and EMI NetBank `v2.3.8` now provide the encrypted evidence contract
and mapping, and x-change is integrated against them pending its release and
host migration. No coverage, settlement envelope, completion Pay Code,
notification, or business application has been added.

The first live provisioning attempt also proved that purpose isolation fails
closed. The sandbox's legacy `netbank-mobile-v1` address for `09173011987` is
already bound to Account Funding, so x-change refused to bind the same provider
destination to purpose `payment`. The sandbox does not currently have the
preferred `netbank-account-hmac-v2` key configured.

After explicit operator authorization, the otherwise-unbound test mobile
`09175180722` produced characterization address
`01M3656EXH0FWZGJKBPGXCNT8J`. Its persisted posture is `payment`,
`observe_only`, `active`, reusable, static P2M, and without an embedded amount.
The pre-payment provider synchronization returned zero observations, zero
settlements, zero suspense cases, and zero applications. It created no Account
Funding receipt. The QR artifact remains private local characterization
evidence.

The operator then paid ₱5.37 through the reusable QR. NetBank returned one
settled InstaPay observation with a stable provider transaction identifier, a
provider operation identifier, exact-destination verification, PHP currency,
and identical occurred/settled timestamps. Two consecutive synchronizations
converged on one immutable observation. There were zero Account Funding
receipts, zero suspense cases, and zero financial applications. Payer identity
was not retained by the standing-address normalization.

The operator then paid ₱6.23 through the same QR. NetBank returned two distinct
provider transaction identities, proving the QR is reusable for independent
payments. The immutable evidence ledger contains three rows: one for the
second transaction and two payload versions for the first transaction. The
later first-transaction version refined `settled_at` by six seconds without
changing amount, currency, or settled status. Immediate replay added no new
row. Economic-payment identity must therefore be distinct from immutable
evidence-version identity.

`ReduceProviderFundingTransactionEvidence` now projects those immutable
versions into `CanonicalProviderFundingTransactionData`. A read-only run over
the live evidence returned exactly two settled transactions—₱5.37 from two
compatible evidence versions and ₱6.23 from one—with verified destinations.
The reducer emits only hashed transaction/operation/request keys and fails
closed on incompatible economic, routing, identity, time, or status changes.

A redacted live shape check proved both payments contain provider-reported
payer name, source account, and institution code, while explicit payer mobile
is absent. The account is not inferred to be a mobile number. EMI Core now has
an additive encrypted payer-evidence schema and NetBank now maps only those
provider-returned fields with `providerVerified=false`. Raw payer values remain
outside metadata, canonical projections, journals, and broadcasts. The
standing-address adapter also consumes NetBank's bounded multi-page iterator.

Scheduled reusable-address observation is now cadence-bound as well as
batch-bound. Active addresses remain eligible for their full active lifetime,
but only never-checked or due addresses enter each fair oldest-first batch.
NetBank history remains bounded to ten 100-row pages and fails closed when the
bounded view is exhausted.

Reversal semantics are intentionally conservative. Adverse or undocumented
status evolution—including any transition away from settled—is incompatible
canonical evidence and cannot trigger a business side effect. Exact NetBank
mapping is deferred until provider documentation or controlled live evidence
shows whether a reversal mutates the credit or arrives as a separate debit.

Campaign entry is now explicit: ordinary endpoint campaigns use
`pay_code_on_open`, while reusable campaign QR Ph uses
`reusable_payment_qr`. The latter binds one exact campaign template revision
to one active payment-purpose Standing Funding Address and its active static QR
artifact. Provider, currency, amount mode, availability, and permitted-payment
rules are hashed into an immutable configuration. Identical retries converge;
conflicting or stale bindings fail closed.

The first Gate 4 boundary is now implemented. Payment-purpose synchronization
looks up the immutable campaign QR binding and evaluates all immutable versions
of a provider transaction through the canonical reducer. Adverse, unknown, and
incompatible evidence converges on an immutable, replay-safe quarantine.
Campaign rows expose only a redacted aggregate `Needs attention` indicator.
Compatible evidence remains observation-only. Tests prove the quarantine path
does not create or mutate coverage, settlement envelopes, Pay Codes, Account
Funding receipts, Client Funds, wallet transactions, funding settlements, or
Treasury operations.

Gate 4b now persists one immutable recognition per binding and canonical
provider transaction. Recognition is serialized by the immutable binding row
and protected by database uniqueness. It applies only generic binding rules:
settled status, verified destination, provider/address/currency match,
availability, fixed/open amount mode, optional amount bounds, allowed rail,
and maximum-payment capacity. Pending evidence remains observation-only;
settled rule failures become durable attention records. A DTO-backed,
redacted event is emitted after commit. Recognition does not credit or move
funds and does not create coverage, an envelope, a Pay Code, or a message.

Redacted evidence: [Gate 1 live characterization report](reports/001-netbank-live-characterization.md).

## Settled Decisions

- Endpoint campaigns and campaign QR Ph are separate explicit entry modes.
- Payment monitoring remains a fact observer, not a business-application
  engine.
- `ProvisionalCoverage` is the authoritative legal/operational fact.
- The settlement envelope is created atomically with coverage and contains an
  immutable coverage snapshot/reference in its first version.
- The initial driver key is
  `aui.personal-accident.provisional-cover@1.0.0`.
- The driver is deferred until provider evidence and generic envelope
  contracts are ready.
- Completion Pay Codes are zero-denominated and use the existing `/x/claim`
  execution model.

## Evidence at This Checkpoint

Protected by `StandingFundingAddressProtocolTest`:

- payment-purpose observations are classified;
- duplicate polling does not duplicate immutable provider observations;
- no Account Funding receipt is created;
- no Client Funds balance changes;
- no Treasury inventory operation is created.
- adverse bound-campaign observations create one durable attention record;
- the synchronization result remains unapplied and no financial credit occurs.

Protected by `BindCampaignPaymentQrTest` and the Cockpit frontend suite:

- adverse, unknown, and incompatible evidence is classified and replay-safe;
- quarantine records are immutable;
- compatible settled evidence does not create attention;
- Cockpit exposes an aggregate attention count without raw provider evidence;
- all measured financial and issuance side-effect counts remain unchanged.
- qualifying fixed and open payments converge on one recognition;
- pending evidence waits without a terminal decision;
- amount and maximum-payment failures enter quarantine;
- replay emits no duplicate recognition event;
- broadcast payloads omit raw provider transaction and payer evidence.

Protected by `CampaignQrPhPlanTest`:

- package ownership is explicit;
- coverage/envelope atomicity is explicit;
- the driver and field-ownership boundaries are explicit;
- live payment is not implicitly authorized.

## Open Questions Requiring Provider Evidence

1. Which NetBank identifier is stable across list/detail/webhook views?
2. Can one QR receive multiple independent transactions until explicit expiry?
3. How are reversals, returns, and late settlement represented?
4. Which payer fields are reliably present and provider-verified?
5. What pagination/window rules prevent missed or duplicated observations?
6. Does NetBank impose an expiry independent of x-change campaign policy?

## Stop Conditions

Stop before business recognition if any of these remain ambiguous:

- provider transaction identity;
- exact campaign/address binding;
- settlement finality;
- amount or currency interpretation;
- replay/idempotency behavior; or
- evidence provenance.

## Next Controlled Gate

Gate 5a is complete. A recognized payment can now be bound explicitly to one
immutable generic `ProvisionalCoverage` and one settlement envelope whose first
payload version already contains the coverage reference and snapshots. The two
facts commit atomically; identical replay returns them, conflicting replay is
rejected, and rollback leaves neither fact behind. The contract validates the
exact driver/version and schema before persistence and emits only redacted,
after-commit x-change audit/broadcast evidence.

Gate 5b is also complete. An explicitly selected, registered
`CampaignCoverageDriverContract` now owns the eligibility decision and coverage
terms. Exact driver/version lookup fails closed, an ineligible decision stops
without persistence, mismatched term identity is rejected, and eligible replay
delegates to Gate 5a's atomic/idempotent binder. The registry ships empty; no AUI
driver or automatic recognition listener has been installed.

Gate 6a is complete. One persisted coverage can now issue exactly one generic,
zero-denominated completion Pay Code through an immutable link. The issuer must
own the bound payment address; coverage/envelope driver identities and the
embedded coverage reference must agree. Identical replay converges, conflicting
replay fails, and voucher/link persistence is atomic. The dedicated
`campaign_coverage_completion` driver uses normal `/x/claim` evidence and
redemption while an explicit execution-only policy suppresses external payout.
Tests prove there is no Account Funding, Treasury, funding settlement, or
non-zero wallet movement.

Gate 6b is complete. A finalized completion claim now creates one immutable
evidence projection and one new envelope payload version. The public payload is
a redacted manifest containing requirement keys, evidence kinds/statuses,
opaque references, hashes, and timestamps; raw answers, mobile numbers, names,
and private artifact paths are excluded. The private correlation snapshot is
encrypted. Exact replay converges without another envelope version or event,
changed evidence fails closed, and forced projection failure rolls back the
envelope version and audit record. The projection emits one redacted
after-commit event and creates no financial movement.

Known package-boundary debt: settlement-envelope's generic `PayloadUpdated`
event is dispatched inside its update transaction. Gate 6b deliberately adds
no listener or external side effect to it. Any package-wide after-commit change
must be handled independently with settlement-envelope regression coverage.

Gate 7a is complete. The reserved
`aui.personal-accident.provisional-cover@1.0.0` adapter is resolved through an
exact-version, duplicate-rejecting registry and validates the full immutable
projection, claim, issuance, coverage, recognition, envelope, and payload
version chain. It returns a deterministic fingerprint and idempotency key.
Safe references and economic facts are separated from applicant values, which
exist only in a private, memory-only preparation property with no generic
serialization surface. Tests prove deterministic replay, unavailable and
duplicate driver rejection, identity mismatch rejection, no HTTP call, no new
domain event, no envelope version, and no financial movement.

Gate 7b is complete. One completion projection can create one durable request
under a default-deny, domain-specific authority. The campaign owner/maker and
independent checker are explicitly configured, and a separately authorized
recorder can persist one immutable `succeeded`, `failed`, or `indeterminate`
outcome. Safe context is queryable; applicant and private result evidence are
encrypted and hidden. Exact replay converges, conflicting replay fails closed,
all three lifecycle events are redacted and after-commit, and forced outcome
failure leaves the request authorized with no partial terminal record. No HTTP,
queue, message, policy document, envelope mutation, or financial movement was
added.

Gate 7c is complete as a provider-neutral, fail-closed readiness boundary.
Only an authorized request with an enabled, complete disposition for its exact
driver version can report ready. The disposition must explicitly cover the
accepted contract and schemas, idempotency, timeouts, retry policy, ambiguous
outcomes, reconciliation, and a configured credential reference. The result
contains only safe missing-field names and a deterministic fingerprint; it
does not expose credential references or values. Default, incomplete,
disabled, wrong-version, and non-authorized cases remain not ready. Readiness
inspection performs no HTTP, queue, persistence, envelope, outcome, or
financial side effect.

Gate 7d is complete as the package-owned intake mechanism. Transport
dispositions are now typed, schema-versioned, exact-driver artifacts with
strict HTTPS endpoint, schema-digest, timeout, unknown-field, credential
reference, and acceptance-provenance validation. Normalization produces a
stable order-independent fingerprint. Embedded credential values are rejected;
runtime readiness checks only presence of the referenced Laravel configuration
secret and does not expose its reference or value. All invalid or absent
artifacts remain fail-closed, with no HTTP or domain mutation.

No authoritative AUI disposition is included. The next gate therefore depends
on AUI supplying its real contract and an authorized architecture acceptance.
Only then can the package add a provider adapter and governed dispatch path.

Gate 7e is complete. Operators can now generate a disabled, unaccepted,
secret-free local YAML template and validate a completed local disposition
through redacted Artisan output. The generator refuses overwrite; the
validator refuses URLs and returns only driver identity plus the stable
fingerprint. Neither command resolves credentials, activates transport, calls
HTTP, queues work, or mutates persistence. The package-owned reference template
is available under `resources/policy-completion-transports` and may be
published with the `x-change-policy-completion-transport` tag.

The external blocker is unchanged: AUI must supply and formally accept the real
contract artifact before any adapter or dispatch implementation begins.

Gate 8a is complete as the first operator-facing backend contract. A bounded,
owner-scoped `x-change.campaign-policy-lifecycle.v1` projection now expresses
the safe lifecycle from payment recognition through provisional coverage,
completion claim evidence, maker/checker governance, and terminal outcome.
Failed and indeterminate outcomes are explicitly attention-bearing. The
projection is read-only and excludes applicant/contact data, provider keys,
actor identities, approvals, snapshots, hashes, private payloads, credentials,
and transport readiness.

Gate 8b is complete. The authenticated Campaigns workspace now links to a
dedicated owner-scoped Policy lifecycle page. It renders only the Gate 8a DTO,
handles empty and nullable intermediate states, highlights failed and
indeterminate outcomes, and provides no approval, retry, dispatch, provider,
transport, or policy mutation controls.

Gate 8c is complete as local presentation acceptance. The lifecycle page now
keeps long safe values inside responsive containers, uses accurate success,
progress, and attention iconography, presents coverage amount/status and safe
policy result codes, and replaces ambiguous nullable wording with contextual
states. Representative lifecycle stages plus the empty state are covered.

Gate 8d is complete. The issued completion Pay Code conditionally links to the
existing authenticated Pay Code detail route through Wayfinder. That route
independently applies owner-aware voucher access. Other lifecycle references
remain noninteractive because they lack an authoritative owner-scoped detail
surface. No mutation or transport authority was introduced.

Gate 9a is complete. Starting the AUI browser scenario now creates a durable,
versioned run identity on its owner-scoped endpoint campaign and opens a
polling execution ledger. The ledger derives its checkpoints and artifact
links from authoritative campaign, template, Pay Code, claim, payment,
collection, and lifecycle records. It declares whether evidence is observed,
whether money is real, and whether the driver or insurer contract is only a
demonstration. Private evidence and provider material are excluded.

The exact AUI provisional-cover YAML is now a package-owned published
demonstration driver. It does not claim an accepted insurer contract. The run
stops honestly after voucher collection until Gate 9b provides a canonical,
idempotent settlement-collection-to-campaign-recognition bridge. No simulated
provider settlement or financial mutation was added in Gate 9a.

Gate 9d is complete. The scenario continues through the ordinary claim pipeline
for the zero-value completion Pay Code. A successful claim creates one immutable
evidence projection, one additional envelope payload version, and the lifecycle
stage `claim_evidence_ready`. Replay is idempotent, while applicant names, mobile
numbers, and private artifact paths stay outside the public envelope and runner
projection.

The run deliberately remains active at a `waiting_for_person` governance
checkpoint. Maker/checker request and approval form the next controlled gate.
Provider transport and policy delivery remain blocked until an authoritative
insurer contract is accepted. No financial or provider side effect was added.

Gate 9e is complete. The scenario owner can produce the canonical maker request,
and an independent configured checker can authorize it through the existing
replay-safe domain actions. The execution ledger shows these as distinct
checkpoints and adds a non-linked safe request artifact. It exposes no applicant
evidence, actor identity, authorization reference, or approval reference.

The accepted stopping state is `policy_authorized` with the scenario still
running. No insurer transport, terminal outcome, policy document delivery,
envelope mutation, provider call, collection, or financial movement occurs.
Transport remains fail-closed until an authoritative insurer contract and
disposition are accepted.

Gate 9f is complete as a local demonstration-response contract. The package
now owns a strict JSON Schema and deterministic responder for the reserved AUI
driver. It returns an `AUI-DEMO-*` reference and explicitly states
`issued_demo`, `demonstration_only`, and `document_ready=false`. Exact replay
returns the same response; wrong-driver and missing-evidence requests fail
closed. Applicant values never enter the safe response or outcome projection.

The responder remains transport-free. Gate 9g now gives the owner-scoped
browser ledger a local demonstration action only when the request is
checker-authorized, the feature is explicitly enabled, and the authenticated
operator has outcome-recorder authority. It delegates persistence to the
existing governed, replay-safe terminal-outcome action.

The lifecycle becomes `policy_succeeded` with
`result_code=policy_issued_demo`; replay creates no second outcome. The ledger
shows only a safe outcome reference and continues to exclude applicant
evidence. No HTTP/Pipedream call, insurer message, policy document, envelope
mutation, collection, or financial movement occurs. The next boundary is an
accepted test-transport disposition, not a real insurer integration.

Gate 9h is complete as a constrained Pipedream test-transport boundary. The
adapter remains dormant unless an exact accepted disposition and referenced
credential are configured. It permits only a `pipedream-test` provider at a
Pipedream HTTPS host, uses the canonical idempotency key, enforces bounded
timeouts, and validates the strict demonstration response without retrying.

The outbound DTO deliberately withholds all applicant/contact/evidence fields.
Only the user-authorized references, amounts, timestamps, fingerprint, and
idempotency key may leave X-Change. This gate is verified with a mocked HTTP
transport: no live request, outcome persistence, policy document, envelope
mutation, collection, or financial movement occurs. A live characterization
requires an explicit endpoint/credential disposition and a separate controlled
execution approval.

Gate 9i is complete locally. The AUI demonstration product is now explicit:
Cubao to Lucena Personal Accident Plan, ₱50.00 premium, ₱5,000.00 insured
amount, and 24 hours of provisional coverage beginning at authoritative
settlement. The driver rejects a payment whose amount or currency does not
match the configured product and no longer mistakes premium for insured
amount. The completion Pay Code uses the normal OTP claim flow to collect name,
mobile, email, address, and birth date.

The next boundary is notification initiation. It must not assume the provider
source account is a mobile number. Before an automatic post-payment SMS can be
enabled, the system needs either provider-verified payer mobile evidence or a
separately verified contact acquisition mechanism tied idempotently to the
recognized payment. Printed reusable QR provisioning and policy-document
delivery also remain separate gates.

Gate 10a is complete locally. Campaign QR Ph now has a package-level
provisioning action built from the same Standing Funding Address machinery used
by the Cockpit Funding QR Ph tab. The pinned campaign revision becomes the
stable HMAC input, while the resulting NetBank address is explicitly
`payment`/`observe_only`. The canonical merchant profile and persisted reusable
QR artifact are reused, then bound immutably to the campaign's amount,
availability, and permitted-payment rules.

Focused tests prove exact replay makes only one provider request and creates
one address/binding, while cross-account and invalid-term attempts fail before
the provider boundary. No financial or messaging side effect is introduced.
Gate 10b is complete locally. The Campaigns page now distinguishes a reusable
payment-QR campaign from an ordinary public endpoint. Only the campaign owner
can invoke the throttled provisioning command. The resulting fixed ₱50 QR Ph
is displayed as a campaign payment stamp with enlarge, download, and print
controls; the raw provider artifact remains confined to the authenticated
owner read model. The AUI scenario declares this entry mode explicitly.

The next controlled move is a real low-value GCash/Maya observation against the
new campaign binding, followed by evidence inspection. No notification or
coverage transition may infer payer identity from an unverified provider
account field.

See [the implementation plan](CAMPAIGN_QR_PH_PLAN.md).

## Campaign payment continuation corrective gate — 2026-09-24

The fixed PHP 50 testing payment was recognized automatically on v1.0.42.
Recognition `01M39140N3ED60FJ9T3KCS5YAD` settled at 06:19:24 UTC and was
recognized at 06:19:50 UTC, without quarantine. It had no coverage because
the standing-address path emitted `CampaignPaymentRecognized` without a
registered lifecycle listener.

The corrective slice registers a package listener that queues the existing
coverage/completion processor on `x-change-funding`, after commit. The job
reloads the durable recognition, uses bounded retries and overlap protection,
and preserves the existing transactional idempotency of coverage and issuance.
Only Campaign QR recognitions enter this listener; the individual settlement
payment path retains its existing continuation. A partial issuance failure
must retry even when a coverage record already exists.

Recovery is explicit and scoped to one recognition:

```bash
php artisan x-change:campaigns:resume-payment 01M39140N3ED60FJ9T3KCS5YAD --no-interaction
php artisan x-change:campaigns:resume-payment 01M39140N3ED60FJ9T3KCS5YAD --dispatch --no-interaction
```

The first command only inspects. The second requests queued continuation,
without another provider payment or collection. The funding queue worker must
be running. Re-inspect after processing to obtain the completion Pay Code.
Use this recovery after a missing enqueue as well as for historical recognized
payments; recognition events themselves are emitted only on first creation.

SMS initiation now uses the existing queued, journaled feedback path. Per the
operator's explicit policy, identifiable GCash/Maya Wallet source accounts in
valid Philippine mobile format are the SMS destinations (09, 639, or +639).
This inference does not change canonical provider identity verification.
Unknown institutions, Maya Bank, and malformed numbers are skipped with a
redacted warning. No new identity/KYC prerequisite is introduced for sending.
Stable recognition-based feedback keys reuse delivery evidence on replay;
the existing provider acceptance/recording crash window is not an exactly-once
transport guarantee. Both funding and feedback workers must run. The exact
recovery command reports persisted SMS status as well as the completion code.
The driver remains demonstration-only; a provisional record does not
prove real insurer coverage or policy issuance. Publication, testing-host
adoption, and replay of the above recognition remain the release acceptance
steps for this corrective slice.

Local verification: Campaign QR suite 55 passed / 397 assertions; journaled
feedback, payment-attempt lifecycle, and settlement collection suites 34 passed
/ 196 assertions; standing-address protocol 29 passed / 277 assertions.
After the final SMS-result guard and inline-driver rejection test, focused SMS
coverage passed 10 tests / 42 assertions. All providers were faked or mocked.
Pint and diff whitespace validation passed. No live SMS or recognition replay
has been executed by this slice. Release and testing deployment remain pending.

### Testing release acceptance — 2026-09-24

The preceding pending-release status is superseded by this acceptance.
With explicit user approval, package commit `23973d23` was published as
`v1.0.43`, and host dependency-only commit `895280ea` was pushed. Deployment
`depl-a2d21f97-169d-4c2f-aea5-4c672b3f4d90` succeeded on the testing instance.
Host integration checks passed (3 tests); isolated build publication, asset
doctor, and production build passed. The temporary checkout required explicit
`APP_ENV=local` because it intentionally contained no `.env`. Existing build
annotation/chunk-size warnings remain. Unrelated sandbox edits were preserved.

The exact recognition above was dispatched once using the recovery command.
Workers produced coverage `01M394991D37G1ZF9635FT4FAN` and completion Pay Code
`POLI-W23C`. Feedback delivery `0bed21b4-eb4e-4e0a-a200-74bdc69cb4a9` is `sent`,
with EngageSpark status `ACCEPTED` at `2026-09-24T07:15:12Z`. Provider acceptance
is not handset delivery confirmation. The link opened successfully in the
in-app browser; no claim was submitted. No new payment or collection occurred.
The x-PayOut instance was not changed. Next: user confirms SMS receipt and
completes the personal-details journey, followed by the fake-insurer policy
response gate. This remains demonstration-only, not proof of real insurance.
