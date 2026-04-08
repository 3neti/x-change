# x-change Architectural Note: Redemption / Disbursement Adoption
## Compass v4 map update

## 1. Purpose

This note defines how redemption/disbursement should be brought into the `x-change` package.

The goal is **not** to rebuild redemption from scratch.

The goal is to **package, normalize, and orchestrate** the redemption flow that already exists in the host application (`redeem-x`) and related packages (`voucher`, `form-flow`, contact, payout/disbursement integrations).

This keeps `x-change` aligned with the real production lifecycle:

1. onboard issuer
2. open issuer wallet
3. fund issuer wallet
4. generate pay code
5. redeem / disburse pay code
6. later expose accounting / history / ledger views

---

## 2. Strategic decision

### We will NOT:
- create a second redemption engine
- recreate voucher redemption validation rules
- recreate the post-redemption pipeline
- replace the existing form-flow UX with a new hardcoded UI flow
- rebuild accounting first

### We WILL:
- adopt the host app redemption/disbursement flow
- normalize it behind `x-change` Actions, DTOs, and Contracts
- treat the YAML form-flow driver as part of the redemption contract
- keep the voucher package and host app as the behavioral source of truth
- add a stable package/API surface that external apps and future developers can use

---

## 3. Source-of-truth components

Redemption in the host app is already split into meaningful seams.

### 3.1 UX / flow preparation seam
Defined by:

- `config/form-flow-drivers/voucher-redemption.yaml`
- `DriverService`
- `FormFlowService`

This seam determines:
- which steps appear
- the order of steps
- conditions for step inclusion
- callback URLs
- reference ID format
- UX behavior for wallet, KYC, bio, OTP, location, selfie, signature, splash

### 3.2 Validation seam
Defined by:

- `App\Services\VoucherRedemptionService`
- `LBHurtado\Voucher\Guards\RedemptionGuard`
- underlying Specifications:
    - secret
    - mobile
    - payable/vendor alias
    - required inputs
    - KYC
    - location
    - time window
    - time limit
    - mobile verification

This seam determines:
- whether redemption is allowed
- which validation failures exist
- the canonical redemption context shape

### 3.3 Execution seam
Defined by:

- `App\Actions\Voucher\ProcessRedemption`
- `LBHurtado\Voucher\Actions\RedeemVoucher`

This seam performs:
- processed-state check
- redemption submit tracking
- contact resolution/creation
- KYC check where required
- redemption metadata preparation
- voucher redemption execution

### 3.4 Post-redemption consequences
Defined by:

- `config/voucher-pipeline.php` → `post-redemption`

This seam performs:
- redeemer/cash validation
- input persistence
- OG cache clearing
- envelope sync
- disbursement
- feedback sending

This means the host app already owns the real redemption side effects.

---

## 4. Core architectural insight

### Redemption is not a single-step payout call.
It is a **multi-stage workflow** with two distinct phases:

### Phase A — prepare the redemption UX
- inspect voucher
- determine if redemption may start
- identify required inputs and validations
- transform voucher instructions into a form-flow definition

### Phase B — execute redemption
- retrieve or receive collected data
- normalize field names
- build redemption context
- validate redemption
- run redemption execution
- rely on pipeline for post-redemption effects

Therefore, `x-change` redemption should be modeled as:
- **flow preparation**
- **execution orchestration**

Not as one flat, naïve `redeem()` method only.

---

## 5. Role of the YAML driver

The file:

- `config/form-flow-drivers/voucher-redemption.yaml`

must be treated as a **first-class fixture and contract artifact**.

It is not merely frontend config.

It is the declarative definition of:
- redemption UX structure
- conditional behavior
- callback wiring
- flow reference identity
- data collection expectations

### Architectural consequence
`x-change` must preserve compatibility with this driver-driven UX.

The package should expose redemption preparation in a way that:
- reflects the same requirements
- reflects the same conditional steps
- reflects the same callback model
- does not drift from the host app behavior

---

## 6. Vocabulary direction: voucher “types”

The system currently talks about things like:
- redeemable
- payable
- settlement
- withdrawable
- divisible
- slice mode

We should **not rename legacy terms immediately** in the underlying flow.

Instead, `x-change` should introduce a normalized capability/profile layer first.

### Proposed normalization concept
`VoucherRedemptionProfileData`

Possible fields:
- `instrument_kind`
- `redemption_mode`
- `requires_form_flow`
- `is_divisible`
- `can_withdraw`
- `slice_mode`
- `required_inputs`
- `required_validation`
- `driver_name`

This allows us to:
- map legacy vocabulary safely
- understand real behavior before renaming
- expose a cleaner API without breaking underlying assumptions

---

## 7. Proposed x-change redemption boundary

### 7.1 Preparation boundary
This layer packages redemption discovery and UX preparation.

#### Candidate Actions
- `InspectPayCodeForRedemption`
- `PreparePayCodeRedemptionFlow`

#### Candidate DTOs
- `VoucherRedemptionProfileData`
- `RedemptionRequirementsData`
- `RedemptionFlowData`
- `PrepareRedemptionResultData`

#### Candidate Contract
- `RedemptionFlowPreparationContract`

#### Responsibilities
- inspect voucher state
- determine whether redemption can begin
- identify capabilities and requirements
- produce or normalize form-flow output
- expose callback and reference information

---

### 7.2 Execution boundary
This layer packages actual redemption/disbursement execution.

#### Candidate Action
- `RedeemPayCode`

#### Candidate DTOs
- `RedeemPayCodeResultData`
- `RedeemerData`
- `DisbursementResultData`

#### Candidate Contract
- `RedemptionExecutionContract`

#### Responsibilities
- map collected data to voucher expectations
- build `RedemptionContext`
- validate via `VoucherRedemptionService`
- execute via `ProcessRedemption`
- return normalized success/failure results

---

## 8. Adapter principle

`x-change` should be the **orchestration facade** over host-app behavior.

### x-change package layer
Owns:
- API surface
- DTOs
- Actions
- Contracts
- serialization
- tests
- stable integration boundary

### Host app / package layer
Owns:
- real redemption validation rules
- form-flow generation
- voucher execution semantics
- post-redemption pipeline side effects
- external payout specifics

This separation avoids duplication and protects the existing business behavior.

---

## 9. Testing implications

### 9.1 The YAML driver must be used verbatim as a fixture
Tests should treat the driver as a source of truth for:
- step composition
- callback format
- reference ID pattern
- conditional UX behavior

### 9.2 Preparation tests should verify
Given voucher instructions:
- correct driver is selected
- wallet step always exists
- KYC/bio ordering is preserved
- optional steps appear/disappear correctly
- completion callback matches `/disburse/{code}/complete`

### 9.3 Execution tests should verify
Given normalized collected data:
- field mapping occurs correctly
- redemption context is built correctly
- validation is delegated correctly
- execution is delegated correctly
- result DTO is stable

---

## 10. Recommended sprint order

### Sprint 1 — Redemption discovery and UX preparation
Deliver:
- profile/requirements/flow DTOs
- preparation action(s)
- tests using YAML driver as fixture

### Sprint 2 — Redemption execution adoption
Deliver:
- execution contract
- redeem action
- result DTO
- wrappers/adapters around `VoucherRedemptionService`, `InputFieldMapper`, and `ProcessRedemption`

### Sprint 3 — Lifecycle completion
Deliver:
- end-to-end flow:
    - onboard issuer
    - open wallet
    - fund wallet
    - generate pay code
    - prepare redemption flow
    - redeem/disburse pay code

### Deferred
- transaction history / accounting API
- ledger presentation
- reporting

Reason:
The wallet package already provides the accounting substrate. The more important unfinished business flow is redemption.

---

## 11. Immediate next move

Before scaffolding execution, we should scaffold **redemption preparation** first.

That is the cleanest first step because it:
- captures the UX contract
- captures voucher capability semantics
- uses the real host app driver
- avoids prematurely flattening a multi-step lifecycle into a single endpoint

### Immediate scaffold target
- `VoucherRedemptionProfileData`
- `RedemptionRequirementsData`
- `RedemptionFlowData`
- `PrepareRedemptionResultData`
- `RedemptionFlowPreparationContract`
- `PreparePayCodeRedemptionFlow`

---

## 12. Guiding principle

> Controllers speak JSON.  
> Actions speak DTO.  
> Services may speak arrays internally.  
> Redemption UX is driven by voucher instructions through the form-flow driver.  
> x-change must package this flow, not replace it.
