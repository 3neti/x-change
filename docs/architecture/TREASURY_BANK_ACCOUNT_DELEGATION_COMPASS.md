# Treasury Bank-Account Delegation Compass

## Invariants

- The System Principal remains non-interactive and is never granted a human
  operator capability.
- A capability check is enforced on every mutation server-side; a hidden or
  visible Cockpit button never establishes authority.
- Account connection, funding readiness, issuance readiness, and payment
  execution are distinct states and authorities.
- A user-owned account may not silently fall back to the shared platform
  account.
- Grants are scoped, journaled, revocable, and may expire.

## Operational decision table

| Operation | Required authority |
|---|---|
| View every user's connected accounts | `treasury.bank_accounts.view` (or stronger) |
| Add a pending account | `treasury.bank_accounts.manage` |
| Bind a Treasury connection | `treasury.bank_accounts.configure` |
| Enable funding or issuance | `treasury.bank_accounts.configure` plus existing provider/Treasury safeguards |
| Select default account | The account owner, after readiness |
| Execute a payout | Existing settlement execution authority; these capabilities are insufficient |

## Review questions

Before enabling an account in a host, confirm that the operator grant has an
authorization reference, remains valid, and includes only the minimum scope.
Before granting funding or issuance readiness, confirm the account-specific
Treasury connection and provider evidence. Review grants at their stated
expiry or when an operator changes responsibility.
