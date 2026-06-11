# Claim UX Compiler — Visible Behavior Activation Slices

## Goal

We have preserved the old claim UX while moving ownership into explicit compiler/result contracts.

The next phase is to let the compiler actively control visible behavior:

```text
skip duplicate splash
show countdown consistently
choose approval/OTP UI
route redirect owner explicitly
render compiled form directly
```

---

## A. Skip Duplicate Splash

### A1. Freeze current splash scenarios

Prove current behavior for:

```text
voucher has rider splash
YAML has form-flow splash
voucher has no rider splash
YAML has form-flow splash
voucher has rider splash only
YAML has no splash
```

### A2. Strengthen compiler splash diagnostics

Compiler should emit:

```text
consumed.splash = true/false
diagnostics.duplicate_splash_prevented = true/false
form_flow.skip_stages = ['splash']
```

### A3. Enforce form-flow splash skip

When `claim_experience.options.skip_consumed_splash === true`, form-flow must not render its splash step.

### A4. Add integration proof

Test:

```text
rider splash rendered once
form-flow splash skipped
no duplicate splash in compiled path
```

---

## B. Show Countdown Consistently

### B1. Freeze redirect countdown contract

Compiler should emit redirect metadata:

```text
redirect.owner
redirect.url
redirect.delay_seconds
redirect.show_countdown
```

### B2. Normalize success redirect payload

`ClaimExperiencePayload::redirect(...)` should be the only backend source of redirect props.

### B3. Normalize frontend countdown view model

Success page should decide countdown visibility only from compiled redirect payload.

### B4. Add countdown behavior tests

Test:

```text
show countdown when show_countdown = true
hide countdown when show_countdown = false
use delay_seconds consistently
redirect endpoint is stable
```

---

## C. Choose Approval / OTP UI

### C1. Freeze approval metadata contract

Already mostly done.

Canonical shape:

```text
provider
authorization_type
reference_id
expires_at
otp_required
polling_required
manual_review
message
```

### C2. Connect redemption result to approval metadata

`SubmitPayCodeClaim` or its approval result should emit approval metadata directly.

### C3. Bind provider authorizer

Replace `NullClaimApprovalOtpAuthorizer` with a real provider-aware implementation seam:

```text
Paynamics OTP
PayoutAuthorization
manual approval
polling provider
```

### C4. Add full approval UX tests

Test:

```text
pending + otp_required → OTP form
pending + polling_required → waiting notice
pending + manual_review → manual review notice
completed → Success.vue
failed → OTP error
```

---

## D. Route Redirect Owner Explicitly

### D1. Define redirect owner rules

Compiler should assign exactly one redirect owner:

```text
claim-widget
success-page
x-rider
none
```

### D2. Add backend invariant test

Test:

```text
each compiled experience has at most one redirect owner
redirect phase includes owner/source/status/url
```

### D3. Remove frontend redirect inference

Success.vue and ClaimWidget should not infer redirect ownership from raw rider/voucher payload.

### D4. Add collision tests

Test:

```text
rider redirect + success redirect does not double redirect
countdown appears only for selected owner
x-rider redirect does not fight success-page redirect
```

---

## E. Render Compiled Form Directly

### E1. Freeze compiled form phase shape

Canonical `form_flow` phase should include:

```text
key
owner = form-flow
status
fields
values
validation
submit behavior
```

### E2. Stop treating compiled form as hidden compatibility boundary

ClaimWidget should render compiled form fields directly when active.

### E3. Unify payload normalization

Avoid two competing payload normalizers:

```text
compiled_form payload
legacy form-flow completion payload
SubmitPayCodeClaim payload
```

Choose one shared normalizer or adapter.

### E4. Add direct rendering tests

Test:

```text
compiled form fields render directly
required fields disable submit
valid fields enable submit
submit payload matches SubmitPayCodeClaim expectations
legacy fallback still works
```

---

## F. Final Live Trial Slice

### F1. Create one canonical demo voucher

Scenario:

```text
rider splash
compiled form
OTP approval
success message
countdown redirect
```

### F2. Run targeted confidence tests

```bash
npm run test:frontend
./vendor/bin/pest tests/Feature/Claim
```

### F3. Manual `/x/claim` trial

Validate:

```text
splash appears once
compiled form renders
OTP approval UI appears
OTP submit works
success page hydrates result
countdown appears
redirect happens once
```

---

## Suggested Order

```text
1. Skip duplicate splash
2. Route redirect owner explicitly
3. Show countdown consistently
4. Render compiled form directly
5. Choose approval / OTP UI with real provider binding
6. Final live trial voucher
```

Reason:

```text
Splash and redirect ownership are compiler truth issues.
Countdown depends on redirect ownership.
Direct compiled form rendering depends on stable phase ownership.
Provider OTP binding should come after the UX surface is already stable.
```
