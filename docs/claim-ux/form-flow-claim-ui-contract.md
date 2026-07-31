# Form-Flow Claim UI Contract

This is the safe contract for making x-change claim screens more human-friendly after the shell hands off to form-flow.

## Principle

The form-flow package remains generic. x-change may add claim-specific copy and review context only through explicit metadata.

The gate is:

```json
{
  "claim_workflow": {
    "key": "campaign.officer-authorization.v1"
  }
}
```

No metadata means no x-change-specific UI.

## Metadata Shape

`FormFlowClaimWorkflowMutator` writes the workflow payload in two places:

- `instructions.metadata.claim_workflow`
- `steps[*].config.claim_workflow` for the `wallet_info` form step

Expected fields:

- `key`
- `title`
- `description`
- `confirmation_label`
- `requires_mobile`
- `requires_destination`
- `requires_amount`
- `requires_authenticated_officer`
- `skip_form_flow_splash`
- `review`

The duplicated step-level payload is intentional. The generic form handler forwards resolved step config into the Inertia page, so the Vue screen can render the claim workflow without changing form-flow manager controller internals.

In the turnkey x-change install, the published form-flow `GenericForm.vue` imports the formatter/helper from `@/components/x-change/formFlowClaimWorkflow`. That helper is published by x-change. If form-flow is used outside an x-change host, either omit `claim_workflow` or provide an equivalent helper before enabling the claim workflow panel.

## Current UI Behavior

When `claim_workflow` is present, the generic form may show:

- workflow title and description,
- a small read-only review panel,
- an explanatory sentence for officer authorization or payout collection,
- the configured submit button label.

When `claim_workflow` is absent:

- no claim workflow panel is shown,
- the submit button remains `Continue`,
- existing field rendering, validation, persistence, auto-sync, and handler submission behavior remain unchanged.

## Campaign Officer Authorization

For `campaign.officer-authorization.v1`, the UI should say this is an authorization action, not a beneficiary payout.

The current review payload may include:

- `beneficiary_count`
- `principal_minor`
- `currency`
- `authorization_reference`
- `worksheet_reference`

The form should not collect payout destination fields for this workflow.

## Non-Goals

This scaffold does not rewrite:

- form-flow manager controllers,
- form-flow handler contracts,
- OTP, signature, location, selfie, or KYC handlers,
- claim execution services,
- provider payout logic,
- rider runtime behavior.

## Test Boundary

The backend contract is guarded by `ClaimWorkflowTest`.

The frontend metadata helper is guarded by `formFlowClaimWorkflow.test.ts`.

If future work moves this into form-flow manager or a generic preview/storyboard package, keep the same opt-in rule: claim-specific UI must be activated by explicit metadata, not by guessing from the route or field list.
