# Medicard Gate M1 — Multi-Product Completion Characterization

Date: 2026-10-09

Status: **Complete**

## Findings

The package already has the correct exact-selection seam:

- `CampaignPolicyCompletionDriverRegistry` keys drivers by `driver_id@version`;
- the service provider always registers the built-in AUI preparation driver;
- host-defined preparation drivers can be added through
  `x-change.settlement.campaign_policy_completion_drivers`;
- duplicate identities, blank identities, and unavailable versions fail closed;
- `PrepareCampaignPolicyCompletion` selects from the immutable completion Pay
  Code issuance identity and rejects a driver that returns another identity; and
- existing AUI lifecycle tests already prove deterministic preparation and
  replay through that seam.

Focused characterization added explicit AUI-plus-Medicard coexistence coverage:
5 tests / 9 assertions passed.

## Gaps Before Medicard Runtime

The preparation registry is multi-product, but the automatic demonstration
runtime after preparation is AUI-specific:

- `AutomaticDemonstrationPolicy` authorizes only the AUI driver identity;
- `CompleteAutomaticDemonstrationPolicy` depends directly on the AUI transport
  and response DTO;
- automatic outcome recording accepts the AUI response DTO directly;
- summary eligibility and presentation recognize only `policy_issued_demo`;
- summary SMS wording is AUI policy-oriented; and
- `CampaignWorkflowPublicationSnapshot` publishes only the exact AUI workflow.

These are controlled extension points, not evidence that payment recognition,
settlement envelopes, Pay Codes, claims, or persistence must be forked.

## Gate M2 Design Decision

Gate M2 will introduce a built-in Medicard coverage/preparation driver pair and
a small exact-identity automatic-demonstration responder registry. A responder
will accept `PolicyCompletionPreparationData` and return generic
`PolicyCompletionOutcomeData`; AUI and Medicard will each own their result code
and safe/private result construction.

The following remain shared:

- `PolicyCompletionRequest` and `PolicyCompletionOutcome` persistence;
- authorization mode and immutable provenance;
- outcome hashing and replay behavior;
- completion claim evidence projections; and
- `PolicyCompletionOutcomeRecorded` journal/event semantics.

The internal `PolicyCompletion*` class and table names are accepted as legacy
implementation vocabulary for this demonstration. Renaming them would create a
large migration with no customer-visible benefit and is deferred to a future
generic product-completion evolution. Customer and operator presentation must
use “Demo Benefit Summary,” never “policy,” for Medicard.

## Safety Decision

Automatic completion remains disabled by default. Enabling it requires both:

1. a supported exact driver identity; and
2. an explicit campaign reference in the existing allow-list.

An unknown driver, unsupported version, missing responder, result-code mismatch,
or campaign outside the allow-list must fail before an external or persistent
completion effect.
