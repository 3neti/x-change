# Quick Generate: Settlement, Execution, and Metadata

Status: architecture note / contract integration updated  
Date: 2026-07-15  
Scope: Quick Generate instruction semantics and execution-engine integration boundary

## Purpose

The Quick Generate `Settlement, execution, and metadata` card is an advanced operator surface for voucher instruction fields that are not part of the common Money Changer flow.

It does not execute drivers, call providers, move money, write journal entries, run actions, send feedback, or mutate campaigns. It only prepares operator-submitted instruction data for the existing x-change `GeneratePayCode` handoff.

## Current Card Mapping

The card currently contributes to three payload areas.

### Settlement fields

These fields describe settlement-oriented contract shape and payment collection rules:

```json
{
  "voucher_type": "settlement",
  "target_amount": 5000,
  "rules": {
    "min_payment": 500,
    "max_payment": 5000,
    "allow_overpayment": true,
    "auto_close_on_full_payment": true
  }
}
```

Current behavior:

- `voucher_type` is omitted for the default `redeemable` value.
- `target_amount` is submitted only when it is positive.
- `rules` is submitted only when at least one settlement rule is present.
- `settlement_rail` belongs to `cash.settlement_rail`; it is not an execution-driver selector.

### Execution instruction

Execution instruction fields are opt-in. If `Include execution instruction` is off, no explicit execution block is submitted and voucher should use default legacy-compatible execution behavior.

When enabled, the card submits:

```json
{
  "execution": {
    "schema": "voucher.execution.v1",
    "driver": "default",
    "mode": "optional-mode",
    "pipeline": ["optional-step"],
    "fallback": "optional-fallback",
    "visibility": ["operator"],
    "metadata": {
      "operator_note": "optional note"
    }
  }
}
```

Current behavior:

- `schema` defaults to `voucher.execution.v1`.
- `driver` defaults to `default`.
- `pipeline` and `visibility` are comma-delimited operator inputs normalized to arrays.
- `metadata.operator_note` is operator context only; metadata is not authority.

### Metadata fields

Metadata fields provide product and routing context:

```json
{
  "metadata": {
    "flow_type": "settlement-envelope",
    "issuer_id": "issuer-123",
    "collection_wallet_id": "wallet-123"
  }
}
```

Current behavior:

- `flow_type` helps x-change classify product flow.
- `issuer_id` and `collection_wallet_id` help x-change resolve collection context.
- Metadata is context, not authority; downstream services must still enforce authorization, readiness, wallet policy, and provider policy.

## Runtime Data Flow

The current Settlement OS flow is:

```text
Cockpit Quick Generate form
    ↓
operator-safe payload builder
    ↓
existing x-change GeneratePayCode handoff
    ↓
x-change PayCodeIssuanceService
    ↓
voucher VoucherInstructionsData
    ↓
persisted voucher instructions and metadata
    ↓
claim / redemption / execution reads the persisted instructions
```

Cockpit prepares the payload. x-change performs the existing issuance handoff. Voucher owns execution semantics.

## Settlement Rail Policy

Quick Generate presents the payout provider and settlement rail as different concepts. The provider is the configured service that executes a payout; the rail is the transfer network used by that provider. `NetBank` is therefore a provider label, never a settlement-rail value.

The operator may choose `Automatic`, `InstaPay`, or `PESONet` for a Cash Payout:

- `Automatic` omits `cash.settlement_rail`. x-change resolves the rail from the actual withdrawal amount when the payout request is created.
- `InstaPay` persists `cash.settlement_rail=INSTAPAY` and remains fixed through claim presentation, dispatch, retry, reconciliation, and journal evidence.
- `PESONet` persists `cash.settlement_rail=PESONET` and remains fixed through the same lifecycle.
- Account Funding does not submit a settlement rail because it does not create a recipient bank payout.

Automatic intentionally resolves per actual withdrawal rather than at issuance. This preserves correct behavior for open and divisible Pay Codes: different slices may resolve to different rails when their amounts cross the configured threshold. The resolved rail is recorded on each disbursement attempt before any provider call, and a retry reuses that persisted attempt rail rather than recalculating it after configuration changes.

Cockpit obtains enabled rails, amount limits, provider transfer costs, and safe availability reasons from the package capability read model. Rendering and estimation read configuration only; they do not contact the provider. Provider transfer cost remains distinct from the x-change instruction price and the issuer's fee strategy.

## Current Execution OS Capability

The voucher execution runtime currently has these capabilities:

- `ExecutionInstructionData` models explicit execution instructions.
- `ExecutionContextData` carries voucher, contact, code, instruction, and correlation context into execution.
- `ExecutionResultData` returns structured execution output and assigns a durable `execution_id`.
- `ExecutionEngine` resolves and executes the configured driver.
- `ExecutionDriverContract` defines the driver interface.
- `ExecutionDriverRegistry` owns driver registration and resolution.
- `UnknownExecutionDriverException` fails closed for unknown explicit drivers before side effects.
- Registered drivers are `default`, `settlement_envelope`, and `stored_value`.
- Legacy vouchers without explicit execution blocks resolve to default behavior.
- Public voucher redemption hydrates persisted execution instructions before driver resolution.
- `ExecutionPipelineRuntime` and `ExecutionPipelineStepRegistry` allow drivers to compose modular internal pipeline steps.
- Explicit execution schemas are rejected unless they match `voucher.execution.v1`.
- x-change binds concrete gateway adapters for voucher-owned `SettlementEnvelopeExecutionGateway` and `StoredValueExecutionGateway`.
- Lifecycle scenarios now demonstrate `settlement_envelope` and `stored_value` execution through the voucher-owned `ExecutionEngine` using x-change gateway bindings.

The current execution path is:

```text
Voucher redemption
    ↓
ExecutionContextData
    ↓
ExecutionEngine
    ↓
ExecutionDriverRegistry
    ↓
ExecutionDriverContract
    ↓
ExecutionResultData
```

## How Settlement OS Packages Consume Instructions

| Layer | Responsibility |
| --- | --- |
| voucher | Owns execution semantics, execution instructions, driver resolution, driver execution, and execution results. |
| x-change | Owns product orchestration, Cockpit UX, campaign context, claim UX, provider integration seams, and the existing issuance handoff. |
| cash / wallet / provider packages | Own money mechanics, rail constraints, balances, minimum disbursement policy, and provider-specific behavior behind contracts/gateways. |
| x-journal | May record execution outcomes later; x-journal is not required by the execution engine. |
| x-action | May present workflow continuations from outcomes/read models; it must not execute money. |
| x-feedback | May communicate state from outcomes/read models; it must not own lifecycle truth. |
| x-campaign | May prefill and batch instruction context; it must not reinvent issuance or execution. |

## Current Gaps

The functional specification is scaffolded through the execution-engine migration and now has concrete x-change contract-demo gateway bindings. These gaps remain before production-grade execution integration:

- `XChangeSettlementEnvelopeExecutionGateway` is bound and demonstrable, but production settlement-envelope metadata still needs provider-grade readiness, locking, child-generation, and recovery policy hardening.
- `XChangeStoredValueExecutionGateway` is bound and demonstrable, but production stored-value execution still needs a cash/wallet ledger-backed gateway instead of request-local in-memory state.
- Built-in drivers are not yet decomposed into configured pipeline steps.
- Execution results are structured and correlated by `execution_id`, but persistence and journaling remain deferred.
- Execution visibility fields exist as instruction data, but full host read-model visibility enforcement remains separate.
- Explicit unsupported execution schema rejection exists for `ExecutionInstructionData`; future schema negotiation and migration tooling remain deferred.
- Settlement-envelope production metadata shape now has a canonical nested baseline under `execution.metadata.settlement_envelope`, but non-demo provider metadata remains incomplete.
- Stored-value production metadata shape now has a canonical nested baseline under `execution.metadata.stored_value`, but ledger/provider metadata remains incomplete.
- Typed executable slice instructions do not yet exist in voucher. Current named slices remain x-change/Cockpit metadata until slices become execution units.

## Canonical Metadata Baselines

### Settlement envelope

The current canonical baseline is nested under `execution.metadata.settlement_envelope`:

```json
{
  "execution": {
    "schema": "voucher.execution.v1",
    "driver": "settlement_envelope",
    "metadata": {
      "settlement_envelope": {
        "reference": "ENV-LIFECYCLE-001",
        "driver": "philhealth-bst",
        "readiness_gate": "settleable",
        "child_generation": "from_envelope",
        "auto_redeem_children": false,
        "fallback_to_claim": true,
        "payload": {},
        "documents": {},
        "checklist": {}
      }
    }
  }
}
```

Voucher owns execution and driver dispatch. x-change owns the gateway binding that maps this metadata into settlement-envelope readiness checks and lock/read-model behavior.

### Stored value

The current canonical baseline is nested under `execution.metadata.stored_value`:

```json
{
  "execution": {
    "schema": "voucher.execution.v1",
    "driver": "stored_value",
    "metadata": {
      "stored_value": {
        "reference": "SV-LIFECYCLE-001",
        "initial_balance": 10000,
        "max_balance": 10000,
        "replenishable": true,
        "otp_required_above": 5000
      }
    }
  }
}
```

The current x-change gateway demonstrates activation/spend/replenish contracts in memory. A production gateway must delegate balance and transaction mutation to wallet/cash/provider infrastructure.

## Lifecycle Demonstration

Two lifecycle scenarios exercise the execution-engine contracts end-to-end:

- `execution_settlement_envelope_contract_demo`
- `execution_stored_value_contract_demo`

Both scenarios issue a voucher through the existing x-change GeneratePayCode handoff, hydrate the persisted execution instruction from voucher, execute through voucher `ExecutionEngine`, and report `execution_id`, `driver`, `status`, `events`, and `metadata` in the JSON lifecycle output.

## Boundary Rules

- Voucher owns execution semantics.
- Cockpit does not execute drivers.
- x-change does not reinterpret voucher execution instructions as driver behavior.
- Claim UX prepares evidence; it does not decide execution consequences.
- x-journal is not required by the execution engine.
- Metadata is not authority.
- Settlement Envelope is a readiness and authorization participant, not the execution engine.
- Stored Value is driver behavior, not a new voucher species.
