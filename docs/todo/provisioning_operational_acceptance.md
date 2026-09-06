# Provisioning Operational Acceptance and Documentation Closure

## Status

The x-provisioning architecture and implementation are complete. The remaining
work is controlled operational acceptance and final documentation closure—not
additional provisioning-domain machinery.

## Remaining acceptance

- grant named maker and checker operators the activation and revocation
  capabilities required for acceptance;
- complete one live lifecycle through request, independent approval,
  invitation issuance, delivery, verified acceptance, and exact-capability
  activation;
- verify the activated recipient sees only the authority carried by the
  immutable envelope;
- revoke the envelope and prove only envelope-derived authority is removed;
- activate a same-subject, same-profile replacement before superseding its
  predecessor;
- complete one governed production API mandate and one-time credential
  ceremony, then verify token use, suspension, and terminal revocation;
- verify scheduled invitation expiry in the deployed runtime; and
- close the remaining full-suite baseline failures before claiming a fully
  green repository.

## Documentation closure

- expand the package README into a standalone operator and developer runbook;
- incorporate the final live maker-checker evidence and screenshots into the
  walkthrough; and
- record the accepted operator capability assignments and API mandate used for
  the controlled environment without publishing secrets or bearer tokens.

## Explicit boundary

Institution Funds classification and Account Grants are adjacent Treasury
operations. They are not unfinished x-provisioning responsibilities.
Provisioning establishes approved identity and authority; it does not classify
liquidity, allocate Client Funds, call a provider, or move money.

