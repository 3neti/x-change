# Claim Storyboard QA Batch Index

## What It Is

The QA batch index renderer is the one-command gallery for the public claim
storyboards.

Instead of rendering and opening each scenario one at a time, the batch command
reads the QA matrix, renders every safe available lane, and writes one HTML
index with links to each storyboard.

## Command

```bash
php artisan xchange:claim-walkthrough \
  --qa-batch \
  --preview-cache \
  --profile=qa \
  --json
```

Use `--refresh-preview` when you want to force fresh artifacts instead of
reusing cached previews.

Use `--run-id=some-readable-id` when you want a stable folder name for a QA
pass.

## Output

The command writes a batch folder under:

```text
storage/app/x-change/claim-preview-batches/{run_id}
```

The important files are:

```text
claim-walkthrough-qa-batch.html
claim-walkthrough-qa-batch.json
claim-walkthrough-qa-review.md
```

The HTML file is the human-facing index. It links to every generated storyboard
HTML and PDF artifact.

The JSON file is the machine-readable manifest for tools and AI agents.

The Markdown review worksheet is the human-editable QA artifact. It repeats each
storyboard lane with the allowed reviewer statuses, notes space, storyboard
links, and checklist items.

The JSON manifest also includes `artifacts.view_options`:

- `default` points to the HTML index;
- `json` points to the JSON manifest;
- `review` points to the Markdown review worksheet;
- `folder` points to the artifact folder;
- `current_app` exposes raw paths for the active Laravel app.

The JSON manifest also includes `review_checklist`. The HTML index renders the
same checklist inside every storyboard card so QA reviewers can use the batch as
a lightweight acceptance worksheet, not just a gallery of links.

Every manifest entry also includes a `review` object:

- `status` starts as `unreviewed`;
- `allowed_statuses` are `pass`, `needs_fix`, and `blocker`;
- `notes` starts blank for reviewer comments.

The HTML index renders those review states as a static worksheet. Each
storyboard lane has a Reviewer status area with outcome choices and a notes box.
It also links to `claim-walkthrough-qa-review.md` for reviewers who prefer a
plain text acceptance worksheet.

Review checklist:

- frames represent meaningful visible states without duplicate-looking steps;
- no visible overlap, clipped text, or awkward scroll requirement in the
  captured claim UI;
- primary action and next step are obvious for a first-time redeemer;
- handler permission, retry, and cancel copy is understandable where the lane
  includes a handler;
- Pay Code, amount, and any slice context are readable before submission;
- no provider call, Cockpit route, or real money movement appears in the
  preview.

## Safety Contract

The batch renderer is intentionally conservative.

It only renders matrix entries that are:

- `status=available`;
- `money_movement=false`;
- `submit_claim=false`;
- backed by a known walkthrough scenario.

It does not:

- submit a claim;
- create real payout movement;
- call payout providers;
- launch Cockpit routes;
- require Paynamics, NetBank, camera, location, or KYC provider credentials.

## Current Coverage

The current batch includes:

- `claim_basic_no_rider`;
- `claim_basic_15_no_inputs_no_riders_no_feedbacks`;
- `claim_basic_15_preview_with_rider`;
- `claim_named_three_slices_preview`;
- `claim_fake_otp_handler_preview`;
- `claim_fake_kyc_handler_preview`;
- `claim_mocked_location_handler_preview`;
- `claim_mocked_selfie_handler_preview`;
- `claim_signature_handler_preview`;
- `claim_paynamics_approval_walkthrough`.

## When To Use It

Use the batch index before changing the public claim UX, form-flow shell, rider
handoff, or handler presentation. It gives reviewers one place to inspect the
expected human journey and compare before/after behavior.

Use the per-card review checklist during UI work so reviewers can mark whether
each lane is visually clear and safe before accepting the slice.

Use the per-card reviewer status when recording the result of a QA pass:

- `pass` means the lane is acceptable for the current UX slice;
- `needs_fix` means the lane is understandable but needs follow-up polish;
- `blocker` means the lane should stop the slice from being accepted.

## Review Ingestion

After a reviewer marks the Markdown worksheet, summarize the result with:

```bash
php artisan xchange:claim-walkthrough \
  --qa-review=storage/app/x-change/claim-preview-batches/{run_id}/claim-walkthrough-qa-review.md \
  --json
```

The command accepts either the Markdown worksheet, the JSON manifest, or the
batch folder. It returns `pass`, `needs_fix`, `blocker`, and `unreviewed` counts.

It also writes a structured review summary JSON artifact beside the worksheet:

```text
claim-walkthrough-qa-review-summary.json
```

Use `--qa-review-output=/absolute/path.json` when a specific summary path is
needed. This keeps reviewer outcomes artifact-first and machine-readable without
requiring database persistence.

## QA Diff

Compare two batch manifests or batch folders with:

```bash
php artisan xchange:claim-walkthrough \
  --qa-diff-from=storage/app/x-change/claim-preview-batches/{old_run_id} \
  --qa-diff-to=storage/app/x-change/claim-preview-batches/{new_run_id} \
  --json
```

The diff highlights added, removed, changed, and unchanged scenarios. A changed
scenario reports whether the artifact fingerprint, storyboard HTML path, or
storyboard PDF path changed.

## Acceptance Report

Generate a final Markdown acceptance report with:

```bash
php artisan xchange:claim-walkthrough \
  --qa-acceptance=storage/app/x-change/claim-preview-batches/{run_id}/claim-walkthrough-qa-review.md \
  --json
```

By default this writes:

```text
claim-ux-acceptance-report.md
```

inside the batch folder. Use `--qa-acceptance-output=/absolute/path.md` when a
specific report path is needed.

The acceptance report includes a Visual Polish Priorities section:

- `blocker` lanes become P0 acceptance blockers;
- `needs_fix` lanes become P1 polish work;
- `unreviewed` lanes become P2 review-completion work;
- if everything passes, the report still reminds reviewers to keep watching
  dense mobile screens, handler capture surfaces, rider handoff, and success
  redirect copy.

Use individual scenario commands when iterating on a single lane.

Use real lifecycle/provider commands only when the goal is provider regression,
wallet movement, or payout verification.

## Relationship To The Matrix

`--qa-matrix` answers "what should we inspect?"

`--qa-batch` answers "show me all inspectable storyboards now."

The batch renderer depends on the matrix safety flags. If a future scenario
becomes money-moving or requires claim submission, it must not be included in
the batch.
