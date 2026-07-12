# Cockpit Wave 53A — Campaign Quick Generate Full URL Readiness Audit

## Status

Completed.

## Scope

Audit whether a successful Quick Generate response already carries a full beneficiary-facing Pay Code URL that Cockpit can show safely after campaign-prefilled issuance.

## Findings

- `GeneratePayCodeResultData` carries `PayCodeLinksData`.
- `PayCodeLinksData` carries:
  - `redeem`
  - `redeem_path`
- `CockpitQuickGenerateMutationRouteShellController::redactedResult()` already includes:
  - `result.links.redeem`
  - `result.links.redeem_path`
  - `result.links.cockpit_detail`
  - `result.links.cockpit_distribution`
- The Vue result panel currently uses the Cockpit detail link but does not visibly present the full beneficiary-facing redeem URL.

## Decision

Wave 53 should surface the existing `result.links.redeem` value in the Quick Generate result panel as an operator-safe beneficiary URL.

## Boundary

Showing the URL does not authorize:

- SMS delivery;
- email delivery;
- webhook delivery;
- campaign mutation;
- bulk issuance;
- provider calls;
- wallet movement;
- journal/action/feedback side effects.

## Expected UI Result

After a successful Quick Generate submit, operators should see a `Beneficiary Pay Code URL` section containing the full redeem URL and the redeem path.
