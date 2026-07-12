# Cockpit Wave 46B — Campaign Recipient Detail / Distribution Backend Prop Bridge

## Purpose

Pass safe campaign-recipient navigation context from the host request query into Pay Code Detail and Distribution Workspace Inertia props.

## Implemented

- Pay Code Detail now accepts campaign navigation query parameters.
- Distribution Workspace now accepts campaign navigation query parameters.
- Both pages receive `campaign_navigation_context` when:
  - `campaign_planning_key` is present;
  - `campaign_execution_id` is present;
  - `campaign_source` is a safe read-only source: `campaign_cockpit` or `x_campaign_adapter`.
- Detail uses destination `pay_code_detail`.
- Distribution uses destination `distribution_workspace`.
- The context includes safe `campaign_id`, `audience_id`, and `recipient_id` when present.

## Boundaries preserved

- No campaign route/controller registration.
- No campaign mutation.
- No bulk issuance.
- No distribution dispatch.
- No feedback delivery.
- No journal writes.
- No provider calls.
- No wallet movement.
- No unsafe payload props.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php --filter="campaign recipient navigation context"
```

## Result

Feature route coverage passed.

## Next checkpoint

`Cockpit Wave 46C — Campaign Recipient Detail Context Rendering`.
