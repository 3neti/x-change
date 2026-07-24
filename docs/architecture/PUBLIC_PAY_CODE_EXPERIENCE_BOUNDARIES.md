# Public Pay Code Experience Boundaries

## Decision

Public Pay Code URLs are separated by money direction and purpose:

| Flow | Canonical human experience | Purpose |
|---|---|---|
| disbursable | `/x/claim/{code}` | deliver value to the beneficiary |
| collectible | `/x/pay/{code}` | collect an exact payment from the payer |
| settlement | flow-aware payment followed by eligible claim execution | collect and then disburse under the settlement contract |
| Account funding | `/x/cockpit/funding` | credit the authenticated owner's internal Account |

`/x/claim/{code}/experience` remains a machine-readable claim-experience
contract. It is not a beneficiary-facing distribution URL.

The first canonical route activated by this decision is
`x-change.claim.show`. Payment and settlement routes must not be advertised
until their durable execution lifecycles are enabled.

## Safety Boundary

- A `GET` public experience is read-only. It may inspect a sanitized Pay Code
  and render an appropriate next step, but it does not start a provider
  operation or move money.
- Outward claim execution never accepts a collectible Pay Code.
- Form-flow completion callbacks are acknowledgement-only. Claim submission
  reloads server-side state, checks that it belongs to the routed Pay Code, and
  serializes duplicate submissions before any outward execution.
- A payment observation records a voucher collection. It never creates an
  Account Funding Receipt or credits the long-lived top-up Account.
- The reusable Account Funding QR remains a standing, open-amount destination.
  It does not generate or consume NetBank VCA registration tokens.
- A NetBank payment uses an expiring, exact-amount VCA. Each new payment
  attempt generates a fresh pre-transaction validation token immediately
  before registration.
- Retrying the same payment attempt reopens its existing encrypted
  instructions. It does not register another VCA.

## Ownership

- `3neti/x-change` owns public route selection, Pay Code capability checks,
  Payment Attempts, collection settlement, presentation, and documentation.
- `3neti/emi-netbank` owns NetBank token, VCA, QR Ph, and transaction-history
  operations behind provider-neutral contracts.
- `3neti/emi-core` owns provider-neutral instruction and observation data.
- The host application owns runtime configuration and published assets only.

## Compatibility

The legacy `/x/claim?code={code}` entry remains available while distributed
links migrate to `/x/claim/{code}`. Existing API consumers may continue using
`/x/claim/{code}/experience` for sanitized compiled claim metadata.
