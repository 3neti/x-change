# Strategy + Implementation Plan — Wire x-change Claim Flow from redeem-x `/disburse`

## Objective

Enable generated x-change vouchers / Pay Codes to be claimed and redeemed through the package-owned x-change UI and lifecycle API.

The first target is the **disburseable voucher flow**:

```text
Generated Pay Code
→ claimant enters code
→ form-flow collects required data
→ x-change consumes form-flow result JSON
→ x-change submits claim
→ voucher is redeemed
→ disbursement is triggered
→ success page is shown
```

The goal is not to invent a new redemption system.

The goal is to **carry forward the proven redeem-x `/disburse` behavior into x-change under the canonical claim vocabulary**.

---

# 1. Rationale

The redeem-x host app already solved the practical redemption UX problem.

It already has:

- code entry;
- voucher validation;
- YAML-driven form-flow;
- conditional collection steps;
- completion callback;
- confirmation;
- final redemption;
- post-redemption disbursement;
- success page;
- rider message / redirect behavior.

x-change should not duplicate or redesign this.

Instead, x-change should package the same lifecycle as:

```text
claim/start
claim/complete
claim/submit
success
redirect
```

This aligns with the newer x-change lifecycle model:

```text
Claim is the public product action.
Redeem and withdraw are internal execution paths.
Disburse is the money-movement consequence.
```

So the strategic renaming is:

```text
/disburse endpoint in redeem-x
        ↓
claim flow in x-change
```

The old `/disburse` flow becomes the production prototype.

The new `/claim` flow becomes the reusable package surface.

---

# 2. Vocabulary Decision

## Public Product Vocabulary

Use:

```text
Claim
Pay Code
Cash Out
```

for UI/API surfaces.

## Internal Domain Vocabulary

Use:

```text
voucher
redeem
withdraw
disburse
settlement
```

inside services, DTOs, validators, and execution classes.

## Rule

Do not expose `/disburse` as the primary package route.

Instead, expose:

```text
/x/claim
/x/pay-codes/{code}/claim/start
/x/pay-codes/{code}/claim/complete
/x/pay-codes/{code}/claim/submit
/x/pay-codes/{code}/success
/x/pay-codes/{code}/redirect
```

A `/disburse` route may exist later as a compatibility alias only.

---

# 3. Strategic Architecture

The claim flow should have three layers.

## Layer A — Claim UX Preparation

This layer prepares the form-flow experience.

```text
code
→ voucher lookup
→ voucher instruction inspection
→ YAML driver transform
→ form-flow start
→ redirect to /form-flow/{flow_id}
```

This is equivalent to redeem-x `DisburseController::start()`.

In x-change, this should become:

```text
StartClaimPageController
StartPayCodeClaimController
PreparePayCodeRedemptionFlow
RedemptionFlowPreparationService
```

---

## Layer B — Claim Completion Context

This layer receives the form-flow completion output.

```text
form-flow collected_data
→ normalize JSON
→ persist/retrieve completion context
→ prepare confirmation payload
```

This corresponds to redeem-x `/disburse/{code}/complete`.

In x-change, this should become:

```text
CompletePayCodeClaimController
LoadPayCodeRedemptionCompletionContext
RedemptionCompletionContextService
```

---

## Layer C — Claim Execution

This layer executes the actual claim.

```text
normalized collected data
→ SubmitPayCodeClaim
→ ClaimExecutionFactory
→ RedeemPayCode or WithdrawPayCode
→ voucher redemption
→ disbursement pipeline
→ success result
```

For the first slice, focus only on:

```text
disburseable voucher
→ full redeem
→ disburse
```

Withdraw, pay, settle, and partial slice flows can come later.

---

# 4. High-Level Flow

## Public Claim Entry

```text
GET /x/claim
```

Displays a simple Pay Code entry page.

The claimant enters:

```text
voucher_code
```

Then submits to:

```text
POST /x/pay-codes/{code}/claim/start
```

or redirects to:

```text
/x/pay-codes/{code}/claim/start
```

depending on whether the page uses GET or POST.

---

## Claim Start

```text
POST /x/pay-codes/{code}/claim/start
```

Responsibilities:

1. resolve voucher by code;
2. verify voucher may begin claim;
3. inspect voucher instructions;
4. build form-flow context;
5. apply the same YAML driver behavior used by redeem-x;
6. create form-flow session;
7. redirect to:

```text
/form-flow/{flow_id}
```

---

## Form-Flow Execution

Owned by form-flow.

It renders conditional steps such as:

```text
wallet / mobile
bank account
KYC
bio fields
OTP
location
selfie
signature
confirmation
```

The form-flow driver remains the UX contract.

Do not hardcode these screens inside x-change.

---

## Claim Complete

```text
POST /x/pay-codes/{code}/claim/complete
```

Responsibilities:

1. receive or retrieve collected form-flow result JSON;
2. normalize fields;
3. store completion context;
4. prepare confirmation data;
5. render x-change confirmation page or return API payload.

---

## Claim Submit

```text
POST /x/pay-codes/{code}/claim/submit
```

Responsibilities:

1. load completion context;
2. build claim payload;
3. call `SubmitPayCodeClaim`;
4. let `ClaimExecutionFactory` choose internal executor;
5. for first slice, execute redeem/disburse path;
6. redirect to success page.

---

## Claim Success

```text
GET /x/pay-codes/{code}/success
```

Shows:

- claim status;
- amount;
- claimant details;
- disbursement status;
- rider message;
- redirect countdown if configured.

---

# 5. Reference ID Strategy

The redeem-x flow likely uses a reference pattern like:

```text
disburse-{CODE}-{timestamp}
```

x-change should switch to:

```text
claim-{CODE}-{timestamp}
```

Example:

```php
$referenceId = sprintf(
    'claim-%s-%s',
    $voucher->code,
    now()->timestamp
);
```

The reference ID should be stable enough for:

- form-flow tracking;
- completion lookup;
- debugging;
- audit metadata.

---

# 6. Driver Strategy

## Use the existing voucher-redemption YAML driver

Do not rebuild UX logic in PHP or Vue.

The YAML driver should remain the source of truth for:

- step ordering;
- conditional step inclusion;
- callback URL;
- reference ID;
- required input expectations;
- handler mapping.

## Required adaptation

Change callback URLs from redeem-x style:

```text
/disburse/{code}/complete
/disburse/{code}/redeem
```

to x-change style:

```text
/x/pay-codes/{code}/claim/complete
/x/pay-codes/{code}/claim/submit
```

If possible, make these configurable through driver variables rather than hardcoded strings.

---

# 7. Collected Data Normalization Strategy

The form-flow result should be treated as raw evidence.

Do not assume every handler returns the same shape.

Create a normalizer service:

```php
FormFlowClaimPayloadNormalizer
```

Responsibilities:

1. accept raw `collected_data`;
2. extract claimant identity fields;
3. extract mobile/wallet fields;
4. extract bank-account fields;
5. extract KYC payload;
6. extract location;
7. extract selfie;
8. extract signature;
9. preserve all original data under `inputs`.

Suggested normalized shape:

```php
[
    'mobile' => '639...',
    'country' => 'PH',

    'bank_account' => [
        'bank_code' => '...',
        'account_number' => '...',
        'account_name' => '...',
    ],

    'inputs' => [
        'name' => '...',
        'email' => '...',
        'address' => '...',
        'birth_date' => '...',
        'gross_monthly_income' => '...',
        'signature' => '...',
        'selfie' => '...',
        'location' => [
            'lat' => ...,
            'lng' => ...,
        ],
        'kyc' => [...],
    ],

    'raw' => [
        // original form-flow output
    ],
]
```

---

# 8. Execution Strategy

## First execution target

Implement only the full redeem/disburse path first.

That means:

```text
disburseable voucher
→ claim submit
→ RedeemPayCode
→ disbursement pipeline
```

Do not handle these yet:

- divisible withdrawal;
- partial slice withdrawal;
- pay flow;
- settle flow;
- complex settlement-envelope finalization.

Those can be added after the first path is green.

---

# 9. Backend Components to Create or Verify

## Controllers

Create or verify:

```text
ClaimEntryPageController
StartPayCodeClaimController
CompletePayCodeClaimController
SubmitPayCodeClaimController
ClaimSuccessPageController
ClaimRedirectController
```

---

## Actions / Services

Create or verify:

```text
PreparePayCodeRedemptionFlow
LoadPayCodeRedemptionCompletionContext
SubmitPayCodeClaim
FormFlowClaimPayloadNormalizer
ClaimCompletionStore
```

---

## Existing lifecycle components to reuse

Prefer using existing lifecycle pieces if already present:

```text
ClaimExecutionFactory
RedeemPayCode
WithdrawPayCode
DefaultRedemptionExecutionService
DefaultWithdrawalExecutionService
VoucherLifecycleService
```

Do not create duplicate execution services if lifecycle already has them.

---

# 10. Frontend Components to Create

Package-owned source of truth:

```text
packages/x-change/resources/js/pages/x-change/claim/Entry.vue
packages/x-change/resources/js/pages/x-change/claim/Confirm.vue
packages/x-change/resources/js/pages/x-change/claim/Success.vue
```

Optional components:

```text
components/x-change/claim/ClaimCodeForm.vue
components/x-change/claim/ClaimSummaryCard.vue
components/x-change/claim/ClaimStatusCard.vue
components/x-change/claim/RiderMessageCard.vue
```

These should use:

```text
XChangeLayout.vue
useXChangeRoutes.ts
```

---

# 11. Route Plan

## Web routes

Add:

```php
Route::prefix('x')->group(function () {
    Route::get('claim', ClaimEntryPageController::class)
        ->name('x-change.claim.entry');

    Route::post('pay-codes/{code}/claim/start', StartPayCodeClaimController::class)
        ->name('x-change.claim.start');

    Route::post('pay-codes/{code}/claim/complete', CompletePayCodeClaimController::class)
        ->name('x-change.claim.complete');

    Route::post('pay-codes/{code}/claim/submit', SubmitPayCodeClaimController::class)
        ->name('x-change.claim.submit');

    Route::get('pay-codes/{code}/success', ClaimSuccessPageController::class)
        ->name('x-change.claim.success');

    Route::get('pay-codes/{code}/redirect', ClaimRedirectController::class)
        ->name('x-change.claim.redirect');
});
```

Important:

- `/x/dashboard` remains authenticated.
- `/x/claim` and claimant routes may need public access.
- Do not put all `/x/*` routes under `auth`.

Split route groups:

```php
// Public claimant routes
Route::prefix('x')->group(...);

// Authenticated operator routes
Route::prefix('x')->middleware(config('x-change.routes.web_middleware'))->group(...);
```

---

# 12. API Route Plan

Add or verify:

```text
POST /api/x/v1/pay-codes/{code}/claim/start
POST /api/x/v1/pay-codes/{code}/claim/complete
POST /api/x/v1/pay-codes/{code}/claim/submit
GET  /api/x/v1/pay-codes/{code}/claim/status
```

The web controllers may call the same actions as the API controllers.

No duplicated logic.

---

# 13. Form-Flow Integration Plan

## Claim Start should call form-flow

Pseudo-flow:

```php
public function __invoke(string $code)
{
    $voucher = $this->vouchers->findByCode($code);

    $result = PreparePayCodeRedemptionFlow::run(
        voucher: $voucher,
        referencePrefix: 'claim',
        completeUrl: route('x-change.claim.complete', $voucher->code),
        submitUrl: route('x-change.claim.submit', $voucher->code),
    );

    return redirect($result->redirectUrl);
}
```

The result should include:

```text
flow_id
reference_id
redirect_url
requirements
```

---

# 14. Completion Handling Plan

When form-flow completes:

```php
public function __invoke(Request $request, string $code)
{
    $context = LoadPayCodeRedemptionCompletionContext::run(
        code: $code,
        flowId: $request->input('flow_id'),
        referenceId: $request->input('reference_id'),
        collectedData: $request->input('collected_data', [])
    );

    return Inertia::render('x-change/claim/Confirm', [
        'claim' => $context->toArray(),
    ]);
}
```

If form-flow stores data in session instead of posting it directly, this action should retrieve it from the configured completion store.

---

# 15. Submit Handling Plan

```php
public function __invoke(Request $request, string $code)
{
    $result = SubmitPayCodeClaim::run(
        code: $code,
        payload: $this->completionStore->get($request->input('reference_id'))
    );

    return redirect()->route('x-change.claim.success', [
        'code' => $code,
    ]);
}
```

`SubmitPayCodeClaim` should own executor selection.

Do not call disbursement provider directly from the controller.

---

# 16. Test Plan

## Unit Tests

Add tests for:

```text
FormFlowClaimPayloadNormalizer
ClaimCompletionStore
PreparePayCodeRedemptionFlow
LoadPayCodeRedemptionCompletionContext
```

---

## Feature Tests

Add tests for:

```text
GET /x/claim renders entry page
POST /x/pay-codes/{code}/claim/start redirects to form-flow
POST /x/pay-codes/{code}/claim/complete returns confirmation page
POST /x/pay-codes/{code}/claim/submit redeems voucher
GET /x/pay-codes/{code}/success renders success page
```

---

## Lifecycle Test

Create one end-to-end test:

```text
generate disburseable voucher
start claim
simulate form-flow collected data
complete claim
submit claim
assert voucher redeemed
assert disbursement requested / recorded
```

---

# 17. First Slice Acceptance Criteria

The first slice is complete when:

- [ ] a generated disburseable Pay Code can be opened from dashboard/show page;
- [ ] claimant can enter Pay Code at `/x/claim`;
- [ ] claim start redirects to form-flow;
- [ ] form-flow uses existing voucher-redemption driver behavior;
- [ ] completion JSON is consumed and normalized;
- [ ] confirmation page renders normalized data;
- [ ] submit executes `SubmitPayCodeClaim`;
- [ ] voucher is marked redeemed;
- [ ] disbursement path is triggered or recorded;
- [ ] success page renders;
- [ ] no host-app `/disburse` dependency remains.

---

# 18. What Not to Do

Do NOT:

- recreate form-flow screens inside x-change;
- hardcode KYC/OTP/location/signature flow in Vue;
- create a second redemption engine;
- call payout provider directly from controller;
- couple claim routes to authenticated dashboard middleware;
- require host app to edit starter-kit pages;
- make `/disburse` the canonical new route;
- flatten redeem/withdraw/pay/settle into one careless method.

---

# 19. Future Expansion

After disburseable full redemption works, extend `ClaimExecutionFactory` to support:

```text
withdrawable voucher
divisible voucher
pay flow
settle flow
settlement-envelope flow
```

Each should use the same outer public API:

```text
claim/start
claim/complete
claim/submit
```

but internally route to the correct executor.

---

# Final Strategic Statement

The x-change claim flow should be a packaged evolution of redeem-x `/disburse`.

The user experience should remain familiar because form-flow and the YAML driver remain the UX authority.

The public product action should be `claim`.

The internal execution may be redeem, withdraw, pay, or settle.

The first implementation slice should prove:

```text
generated disburseable voucher
→ claim
→ form-flow
→ completion JSON
→ submit
→ redeem
→ disburse
→ success
```

Once that is working, all other voucher execution modes can be added behind the same claim surface.
