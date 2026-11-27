# Treasury Bank-Account Delegation Plan

## Purpose

Permit a named, verified human operator to administer user-owned bank-account
connections without making that person the non-interactive System Principal or
granting them authority to execute payments.

## Scope

`x-change` owns the runtime authorization, Cockpit presentation, audited
operator grants, and bank-account readiness boundary. `x-provisioning` owns
the optional governed invitation and activation path for the human role. A
host declares its chosen operators and authorization references privately at
commissioning time.

## Capabilities

| Capability | Permits | Does not permit |
|---|---|---|
| `treasury.bank_accounts.view` | Inspect connected accounts across owners | Add, configure, or move funds |
| `treasury.bank_accounts.manage` | Add a pending account connection for an owner | Mark funding or issuance ready |
| `treasury.bank_accounts.configure` | Bind the separate Treasury connection and complete explicit readiness attestations | Execute a payout, access provider credentials, or change System Principal identity |

`manage` and `configure` imply account-list visibility. Account owners keep
their existing right to view, rename, and select their own ready account.

## Commissioning

1. Verify the human operator's mobile identity through the host's ordinary
   authentication and verification process.
2. Record a delegated authority reference, scope, and review/expiry date.
3. Grant only the capabilities required using
   `x-change:treasury:authorize-operator` or a reviewed provisioning offer.
4. Add the account as **Pending**. Assignment is not readiness.
5. Independently configure issuance and funding after their provider and
   Treasury checks have passed.
6. Revoke or expire the operator authorization when the delegated duty ends.

## Non-goals

- Replacing the System Principal with an interactive person.
- Giving a bank-account administrator payment-execution or credential access.
- Enabling a personal account merely because it was added.
- Silently using the platform shared account when a selected personal account
  is unavailable.
