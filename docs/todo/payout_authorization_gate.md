# Payout Authorization Gate — Strategy and Implementation Plan

## 1. Purpose

This plan introduces a generic **Payout Authorization Gate** into the x-change claim/disbursement lifecycle.

The immediate driver is Paynamics Cash Out, where the payout provider sends an OTP to the issuer before allowing the transfer to proceed.

However, the architecture must not be OTP-specific.

The Payout Authorization Gate should support future authorization challenges such as:

- Payout OTP
- manual approval
- treasury approval
- dual approval
- AML review
- provider fraud hold
- batch approval
- provider-pending authorization

The central idea is:

> Redemption may complete, but payout may require authorization before money leaves the system.

---

## 2. Core Architectural Decision

The Payout Authorization Gate belongs after redemption validation and before disbursement.

Current flow:

```text
SubmitPayCodeClaim::handle()
  → RedeemPayCode::handle()
    → RedeemVoucher::run()
      → VoucherObserver::redeemed()
        → HandleRedeemedVoucher
          → ValidateRedeemerAndCash
          → ValidateRedemptionContract
          → DisburseCash
```

Target flow:

```text
SubmitPayCodeClaim::handle()
  → RedeemPayCode::handle()
    → RedeemVoucher::run()
      → VoucherObserver::redeemed()
        → HandleRedeemedVoucher
          → ValidateRedeemerAndCash
          → ValidateRedemptionContract
          → AuthorizePayout
          → DisburseCash
```

`AuthorizePayout` is a generic pipe.

It asks:

```text
Is this payout authorized to proceed?
```

It does not ask:

```text
Does this payout need OTP?
```

OTP is one possible authorization challenge, not the gate itself.

---

## 3. Domain Distinctions

### Voucher OTP

Voucher OTP belongs to claimant/redeemer evidence collection.

It lives in:

```text
form-flow
voucher instructions
inputs.fields
validation.otp
```

It means:

```text
The claimant must prove something before claiming.
```

### Payout OTP

Payout OTP belongs to issuer/provider authorization.

It lives in:

```text
x-change payout authorization workflow
provider payout challenge
issuer approval dashboard
```

It means:

```text
The issuer or payout provider must authorize money movement.
```

These two must remain separate.

---

## 4. Package Ownership

## 4.1 voucher package

The voucher package owns the pipeline hook.

It should contain only generic pipeline-level authorization delegation.

It should not know:

- Paynamics
- OTP
- issuer dashboard
- notifications
- x-change UI
- provider-specific authorization details

### New / Updated Classes

```php
LBHurtado\Voucher\Pipes\AuthorizePayout
LBHurtado\Voucher\Contracts\PayoutAuthorizationGate
LBHurtado\Voucher\Data\PayoutAuthorizationDecision
LBHurtado\Voucher\Exceptions\PayoutAuthorizationPendingException
```

### `AuthorizePayout` responsibility

```text
- runs before DisburseCash
- delegates to a configured PayoutAuthorizationGate
- continues if payout is authorized or no authorization is required
- pauses/defer disbursement if authorization is required
- records enough context for downstream reconciliation
```

### `PayoutAuthorizationGate` contract

Suggested shape:

```php
interface PayoutAuthorizationGate
{
    public function authorize(Voucher $voucher): PayoutAuthorizationDecision;
}
```

### `PayoutAuthorizationDecision`

Suggested fields:

```php
final class PayoutAuthorizationDecision
{
    public function __construct(
        public readonly bool $authorized,
        public readonly bool $pending = false,
        public readonly ?string $status = null,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {}
}
```

### Pipeline config

```php
'post-redemption' => [
    ValidateRedeemerAndCash::class,
    ValidateRedemptionContract::class,
    AuthorizePayout::class,
    DisburseCash::class,
],
```

---

## 4.2 x-change package

The x-change package owns the actual payout authorization workflow.

It owns:

- pending payout authorization records
- issuer-facing approval UI
- issuer notifications
- approval queue
- provider challenge orchestration
- claim resume/retry behavior
- audit logging
- lifecycle scenario runner integration

### New Model

```php
LBHurtado\XChange\Models\PayoutAuthorization
```

Table:

```text
xchange_payout_authorizations
```

This model must be generic, not OTP-specific.

OTP-specific values should live in JSON payload fields, not top-level schema fields.

### Migration

```php
Schema::create('xchange_payout_authorizations', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->foreignId('voucher_id')->index();
    $table->foreignId('cash_id')->nullable()->index();

    $table->morphs('issuer');
    $table->nullableMorphs('approver');

    $table->string('provider')->index();
    $table->string('provider_request_id')->nullable()->index();
    $table->string('provider_reference')->nullable()->index();

    $table->string('status')->default('created')->index();

    $table->string('authorization_type')->default('payout')->index();
    $table->string('challenge_type')->index();

    $table->decimal('amount', 18, 2);
    $table->string('currency', 3)->default('PHP');

    $table->string('destination_bank_code')->nullable()->index();
    $table->string('destination_account_masked')->nullable();
    $table->string('destination_label')->nullable();

    $table->string('reason')->nullable();

    $table->json('challenge_payload')->nullable();
    $table->json('resolution_payload')->nullable();
    $table->json('metadata')->nullable();

    $table->timestamp('requested_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->timestamp('completed_at')->nullable();

    $table->timestamps();

    $table->unique(
        ['voucher_id', 'provider', 'provider_request_id'],
        'xpa_provider_request_unique'
    );
});
```

### Model

```php
namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayoutAuthorization extends Model
{
    protected $table = 'xchange_payout_authorizations';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'challenge_payload' => 'array',
        'resolution_payload' => 'array',
        'metadata' => 'array',
        'requested_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'failed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(config('voucher.models.voucher'), 'voucher_id');
    }

    public function issuer(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            'created',
            'challenge_requested',
            'awaiting_response',
        ], true);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            'succeeded',
            'failed',
            'expired',
            'cancelled',
            'rejected',
        ], true);
    }
}
```

### Enums

```php
LBHurtado\XChange\Enums\PayoutAuthorizationStatus
LBHurtado\XChange\Enums\PayoutChallengeType
```

Suggested status values:

```text
created
challenge_requested
awaiting_response
approved
processing
succeeded
failed
expired
cancelled
rejected
```

Suggested challenge types:

```text
payout_otp
manual_review
treasury_approval
aml_hold
provider_pending
dual_approval
```

### Services

```php
LBHurtado\XChange\Services\PayoutAuthorizationService
LBHurtado\XChange\Services\PayoutAuthorizationNotifier
```

### `PayoutAuthorizationService`

Responsibilities:

```text
- implement Voucher package PayoutAuthorizationGate
- determine if the selected payout provider requires authorization
- create or reuse pending PayoutAuthorization records
- call EMI provider challenge request
- return PayoutAuthorizationDecision to the voucher pipe
- detect if authorization already exists and has been approved
- allow DisburseCash to continue only when authorized
```

### `PayoutAuthorizationNotifier`

Responsibilities:

```text
- send database notification by default
- optionally send SMS/email later
- notify issuer before provider OTP arrives
- notify issuer after approval
- notify issuer on success/failure/expiry
```

### Actions

```php
CreatePayoutAuthorization
RequestPayoutAuthorizationChallenge
ApprovePayoutAuthorization
RejectPayoutAuthorization
ExpirePayoutAuthorization
ResumeAuthorizedPayout
MarkPayoutAuthorizationSucceeded
MarkPayoutAuthorizationFailed
```

### Notifications

```php
PayoutAuthorizationChallengeExpected
PayoutAuthorizationApproved
PayoutAuthorizationSucceeded
PayoutAuthorizationFailed
PayoutAuthorizationExpired
```

Database channel should be enabled by default.

Initial notification classes may use only:

```php
public function via($notifiable): array
{
    return ['database'];
}
```

Later, configurable channels can be added:

```text
sms
email
webhook
broadcast
```

Do not scaffold Laravel Echo yet.

Create broadcast-ready events, but do not implement real-time broadcasting in the first slice.

### Events

```php
PayoutAuthorizationCreated
PayoutAuthorizationChallengeRequested
PayoutAuthorizationApproved
PayoutAuthorizationSucceeded
PayoutAuthorizationFailed
PayoutAuthorizationExpired
```

These events should be broadcast-compatible later, but should not implement `ShouldBroadcast` yet.

### Controllers

Issuer-facing:

```php
PayoutAuthorizationIndexController
PayoutAuthorizationShowController
PayoutAuthorizationApproveController
PayoutAuthorizationRejectController
```

Suggested routes:

```text
GET  /x/issuer/payout-authorizations
GET  /x/issuer/payout-authorizations/{uuid}
POST /x/issuer/payout-authorizations/{uuid}/approve
POST /x/issuer/payout-authorizations/{uuid}/reject
```

### Vue Pages

```text
pages/x-change/issuer/payout-authorizations/Index.vue
pages/x-change/issuer/payout-authorizations/Show.vue
```

The issuer UI must allow an issuer to match provider OTP messages to pending payouts.

Show:

```text
Pay Code
amount
provider
destination bank
masked account
reason/reference
requested time
expiry time
status
approval input
```

For Paynamics OTP, show:

```text
Amount: PHP 75.00
Destination: GXI ending 1987
Reason: Voucher payout TEST-Z3EL-09173011987-S1
```

### DTOs / Data Objects

```php
PayoutAuthorizationData
PayoutAuthorizationListData
ApprovePayoutAuthorizationData
```

### Config

```php
'payout_authorization' => [
    'enabled' => true,

    'notifications' => [
        'database' => true,
        'sms' => false,
        'email' => false,
        'webhook' => false,
        'broadcast' => false,
    ],

    'default_challenge_ttl_minutes' => 5,

    'issuer_dashboard' => [
        'enabled' => true,
    ],
],
```

---

## 4.3 emi-core package

The emi-core package owns provider-neutral payout challenge contracts and DTOs.

It should not know about:

- x-change UI
- voucher pipeline
- issuer dashboard
- Laravel notifications

### Contracts

```php
LBHurtado\EmiCore\Contracts\SupportsPayoutAuthorization
LBHurtado\EmiCore\Contracts\PayoutChallengeProvider
```

Suggested methods:

```php
public function requiresAuthorization(PayoutRequestData $request): bool;

public function requestAuthorizationChallenge(
    PayoutRequestData $request
): PayoutChallengeResultData;

public function submitAuthorizationChallenge(
    PayoutChallengeSubmissionData $submission
): PayoutAuthorizationResultData;
```

### DTOs

```php
PayoutChallengeResultData
PayoutChallengeSubmissionData
PayoutAuthorizationResultData
```

### `PayoutChallengeResultData`

Suggested fields:

```php
provider
provider_request_id
provider_reference
challenge_type
status
amount
currency
destination_bank_code
destination_account_masked
reason
expires_at
metadata
```

### `PayoutChallengeSubmissionData`

Suggested fields:

```php
provider_request_id
challenge_type
challenge_response
metadata
```

### `PayoutAuthorizationResultData`

Suggested fields:

```php
approved
status
provider_request_id
provider_reference
message
metadata
```

### Enums

```php
PayoutChallengeType
PayoutChallengeStatus
```

Suggested status values:

```text
none
required
requested
awaiting_response
approved
failed
expired
cancelled
```

---

## 4.4 emi-paynamics package

The emi-paynamics package owns Paynamics-specific OTP behavior.

It should not own:

- x-change issuer UI
- notifications
- PayoutAuthorization model
- voucher pipeline decisions

### New / Updated Classes

```php
PaynamicsPayoutAuthorizationProvider
RequestPaynamicsCashOutOtp
SubmitPaynamicsCashOutWithOtp
```

Or update the existing Paynamics payout provider to implement:

```php
SupportsPayoutAuthorization
```

### DTOs

```php
PaynamicsCashOutOtpRequestData
PaynamicsCashOutOtpResponseData
PaynamicsCashOutWithOtpData
```

### Responsibilities

```text
- request Paynamics Cash Out OTP
- parse Paynamics OTP response
- submit Paynamics Cash Out with OTP
- convert Paynamics responses into emi-core DTOs
- expose fake/sandbox behavior for tests
```

### Test/Fake Support

```php
FakePaynamicsPayoutAuthorizationProvider
```

Fake behavior should support:

```text
- always requires OTP
- generates deterministic fake OTP
- rejects invalid OTP
- approves valid OTP
- supports lifecycle scenario runner simulation
```

---

## 5. Notification Strategy

Notification is part of the authorization protocol.

It is not an afterthought.

For Payout OTP, notification solves the operational problem:

```text
many vouchers
many OTP messages
one issuer approval queue
```

### Notification flow

```text
AuthorizePayout
→ create PayoutAuthorization
→ notify issuer via database channel
→ optionally notify via SMS/email
→ request provider OTP
→ issuer receives provider SMS
→ issuer opens x-change dashboard
→ issuer matches OTP to pending payout
→ issuer enters OTP
→ payout resumes
→ issuer receives success/failure notification
```

### Pre-emptive notification copy

```text
A voucher payout needs your approval.

You may receive an OTP from Paynamics shortly.

Amount: PHP 75.00
Destination: GXI ending 1987
Pay Code: TEST-Z3EL
Reason: Voucher payout TEST-Z3EL-09173011987-S1

Enter the OTP only inside your authenticated x-change dashboard.
Never send the OTP to another person.
```

### Success notification copy

```text
Voucher payout completed.

Pay Code: TEST-Z3EL
Amount: PHP 75.00
Destination: GXI ending 1987
Status: Successful
```

### Default notification channel

Database notification should be enabled by default.

```text
database = always on
sms = configurable
email = configurable
webhook = deferred
broadcast = deferred
```

### Feedback instructions

Voucher feedback instructions may include:

```text
sms
email
webhook
```

These are separate from operational payout authorization notifications.

For now:

```text
- acknowledge feedback exists
- leave preemptive plumbing compatible with notification channels
- defer feedback-driven notification strategy
```

---

## 6. Claim Flow Map Update

In `CLAIM_FLOW_MAP.md`, Phase 3 should include the Payout Authorization Gate.

Insert between `ValidateRedemptionContract` and `DisburseCash`:

```text
AuthorizePayout
  ├─ no authorization required
  │    → continue to DisburseCash
  │
  └─ authorization required
       → create PayoutAuthorization
       → notify issuer
       → request provider challenge
       → pause disbursement
       → wait for issuer approval
       → resume DisburseCash
```

Clarify:

```text
Voucher OTP lives in Phase 2 / form-flow.
Payout OTP lives in Phase 3 / claim execution.
```

---

## 7. Lifecycle Scenario Runner

The Lifecycle Scenario Runner becomes the gold-standard live test for this feature.

This feature is not just a unit-level provider test.

It crosses:

```text
voucher
x-change
emi-core
emi-paynamics
notifications
issuer UI
claim resume
audit logging
reconciliation
```

### New scenario

```text
Scenario: Paynamics payout requiring issuer Payout OTP
```

### Scenario flow

```text
1. Create issuer
2. Fund issuer wallet
3. Generate voucher
4. Claim voucher
5. Complete form-flow
6. Submit claim
7. Trigger AuthorizePayout
8. Paynamics requests OTP
9. Create pending PayoutAuthorization
10. Notify issuer
11. Scenario enters WAITING_FOR_PAYOUT_AUTHORIZATION
12. Simulate issuer receiving OTP
13. Simulate issuer entering OTP
14. Approve PayoutAuthorization
15. Resume payout
16. Complete disbursement
17. Verify notification and audit trail
18. Verify final payout metadata
19. Verify rider/success experience
```

### New scenario states

```text
AUTHORIZATION_REQUIRED
CHALLENGE_REQUESTED
WAITING_FOR_PAYOUT_AUTHORIZATION
OTP_EXPECTED
OTP_SUBMITTED
AUTHORIZATION_APPROVED
PAYOUT_RESUMED
DISBURSED
```

### Scenario support classes

```php
RequiresPayoutAuthorization
SimulateProviderOtpChallenge
SimulateIssuerOtpApproval
ResumePayoutAfterAuthorization
AssertPayoutAuthorizationState
AssertPayoutAuthorizationNotification
```

---

## 8. UI Strategy

The issuer UI should be an approval queue, not a one-off OTP prompt.

This is critical because many vouchers may be redeemed close together.

### Index page

```text
Pending Payout Authorizations
```

Columns:

```text
status
pay code
amount
provider
destination
reason/reference
requested at
expires at
actions
```

### Show page

Details:

```text
Pay Code
Voucher ID
Amount
Currency
Provider
Provider request ID
Provider reference
Destination bank
Masked destination account
Reason
Challenge type
Status
Requested at
Expires at
```

Approval form:

```text
Enter Payout OTP
Approve
Reject / Cancel
```

### UX warning

```text
Enter the OTP only inside this authenticated x-change dashboard.
Do not send the OTP to any person.
```

---

## 9. Resume / Retry Strategy

When `AuthorizePayout` pauses disbursement, the voucher remains redeemed.

The payout remains pending authorization.

After approval:

```text
ApprovePayoutAuthorization
→ provider verifies challenge
→ mark authorization approved
→ ResumeAuthorizedPayout
→ continue or retry DisburseCash
```

Two possible implementation options:

### Option A — Retry only DisburseCash

Preferred first implementation.

Since the voucher is already redeemed and validation already passed, approval can directly trigger the disbursement continuation.

```text
approved authorization
→ call DisburseCash-compatible action
→ record payout metadata
```

### Option B — Replay post-redemption pipeline from AuthorizePayout

More complete but more complex.

```text
approved authorization
→ resume pipeline after AuthorizePayout
→ continue to DisburseCash
```

Recommendation:

Start with Option A.

Keep enough metadata to support Option B later.

---

## 10. Audit Trail

Every state transition should be auditable.

Suggested audit events:

```text
payout_authorization.created
payout_authorization.challenge_requested
payout_authorization.notification_sent
payout_authorization.approved
payout_authorization.rejected
payout_authorization.expired
payout_authorization.resume_requested
payout_authorization.disbursement_succeeded
payout_authorization.disbursement_failed
```

Audit metadata should include:

```text
voucher_id
payout_authorization_uuid
provider
provider_request_id
amount
currency
destination_bank_code
destination_account_masked
challenge_type
status
```

Do not log OTP codes.

---

## 11. Security Rules

### Never store OTP code

Do not store the submitted OTP in plaintext.

If absolutely needed for debugging, store only:

```text
otp_submitted = true
submitted_at
provider_response_id
```

### Never show full bank account unless already allowed

Use masked destination account.

Example:

```text
********1987
```

### OTP entry must be authenticated

Issuer must be logged in.

### Authorization must be issuer-scoped

Issuer can only see and approve their own payout authorizations.

### Expiry must be enforced

Expired authorization cannot be approved.

### Idempotency must be enforced

Approving the same authorization twice should not create duplicate payouts.

---

## 12. Testing Plan

### voucher package tests

```text
AuthorizePayout continues when no authorization required
AuthorizePayout pauses when authorization required
AuthorizePayout does not call DisburseCash when pending
AuthorizePayout delegates to PayoutAuthorizationGate
```

### x-change tests

```text
creates PayoutAuthorization record
reuses existing pending authorization
database notification is created by default
issuer can list pending payout authorizations
issuer can approve valid authorization
issuer cannot approve another issuer's authorization
expired authorization cannot be approved
approved authorization resumes payout
failed provider approval marks authorization failed
```

### emi-core tests

```text
DTO serialization
challenge status values
provider contract fake implementation
```

### emi-paynamics tests

```text
requests Cash Out OTP
maps Paynamics response to PayoutChallengeResultData
submits Cash Out with OTP
maps approval success/failure
fake provider accepts deterministic OTP
fake provider rejects invalid OTP
```

### lifecycle scenario runner tests

```text
Paynamics payout requiring OTP completes successfully
multiple vouchers create multiple authorization records
issuer approval queue shows correct matching details
wrong OTP fails without disbursing
valid OTP resumes payout
database notifications are created
final voucher remains redeemed
final payout metadata is recorded
```

---

## 13. Implementation Slices

## Slice 1 — voucher pipeline hook

Deliver:

```text
AuthorizePayout pipe
PayoutAuthorizationGate contract
PayoutAuthorizationDecision DTO
PayoutAuthorizationPendingException or equivalent control signal
voucher-pipeline.php update
unit tests
```

## Slice 2 — x-change persistence foundation

Deliver:

```text
PayoutAuthorization model
xchange_payout_authorizations migration
status enum
challenge type enum
factory
basic tests
```

## Slice 3 — x-change service binding

Deliver:

```text
PayoutAuthorizationService
bind Voucher PayoutAuthorizationGate to x-change implementation
create/reuse pending authorization
return allow/pending decisions
tests
```

## Slice 4 — emi-core challenge contracts

Deliver:

```text
SupportsPayoutAuthorization contract
PayoutChallengeProvider contract
PayoutChallengeResultData
PayoutChallengeSubmissionData
PayoutAuthorizationResultData
enums
fake provider
tests
```

## Slice 5 — emi-paynamics adapter

Deliver:

```text
RequestPaynamicsCashOutOtp
SubmitPaynamicsCashOutWithOtp
Paynamics provider implements emi-core authorization contract
fake OTP mode
tests
```

## Slice 6 — notifications

Deliver:

```text
database notifications enabled by default
PayoutAuthorizationChallengeExpected
PayoutAuthorizationSucceeded
PayoutAuthorizationFailed
event classes
notification tests
```

No Laravel Echo yet.

## Slice 7 — issuer approval UI

Deliver:

```text
routes
controllers
Index.vue
Show.vue
approve endpoint
reject endpoint
authorization policy
feature tests
```

## Slice 8 — resume payout

Deliver:

```text
ApprovePayoutAuthorization
ResumeAuthorizedPayout
provider challenge submission
disbursement resume
idempotency tests
failure tests
```

## Slice 9 — lifecycle scenario runner

Deliver:

```text
Paynamics OTP scenario
multiple voucher scenario
issuer approval queue simulation
wrong OTP simulation
valid OTP simulation
visual scenario state output
```

## Slice 10 — documentation

Deliver updates to:

```text
CLAIM_FLOW_MAP.md
DISBURSE_FLOW_MAP.md
x-change voucher lifecycle guide
emi-paynamics integration docs
lifecycle scenario docs
```

---

## 14. Final Target Architecture

```text
voucher
  → owns AuthorizePayout pipeline hook

x-change
  → owns PayoutAuthorization model, workflow, notifications, UI, audit, lifecycle simulation

emi-core
  → owns generic payout challenge contracts and DTOs

emi-paynamics
  → owns Paynamics-specific OTP request and cash-out-with-OTP behavior

form-flow
  → no role in Payout OTP
```

---

## 15. Final Mental Model

```text
Claimant proves eligibility through form-flow.

Voucher validates redemption contract.

Issuer/provider authorizes payout through Payout Authorization Gate.

Provider executes disbursement.

x-change orchestrates the lifecycle.
```

The Payout Authorization Gate is therefore not merely an OTP feature.

It is the beginning of a durable settlement authorization workflow inside x-change.
