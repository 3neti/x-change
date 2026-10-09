# Medicard Gate M2 — Contract and Driver

Date: 2026-10-09

## Disposition

Gate M2 is complete and green. The package now carries an exact
`medicard.demo-benefit@1.0.0` settlement-envelope definition, a deterministic
coverage/completion driver pair, a versioned sanitized response contract, and
an automatic-demonstration responder selected by exact driver identity.

The implementation is local and demonstration-only. It does not call a
Medicard endpoint and does not create membership, healthcare coverage,
treatment authorization, reimbursement rights, or a financial effect.

## Proven Behavior

- The AUI and Medicard drivers coexist in the same exact-version registries.
- Missing, blank, duplicate, and unsupported identities fail closed.
- A settled PHP 50 Medicard demonstration payment can create one coverage
  envelope and one completion Pay Code through the existing lifecycle.
- Submitted participant evidence produces one deterministic
  `benefit_ready_demo` outcome.
- Immediate replay returns the existing request and outcome without a second
  mutation.
- The Medicard responder sends no HTTP request.
- Existing AUI automatic completion and transport behavior remains green.

## Verification

Focused Medicard and AUI regression suite:

- 17 tests passed;
- 70 assertions; and
- no live payment, SMS, provider, or external Medicard operation.

## Next Gate

Gate M3 makes first-message, completion-message, claim, and private summary
presentation product-aware while preserving the existing claim-token,
feedback, evidence, and redaction boundaries.
