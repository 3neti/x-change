# Cockpit Wave 30A — Legacy Pay Code Index vs Cockpit Explorer Read Model Parity Audit

## Status

Complete.

## Objective

Compare the existing legacy Pay Code index surface with the Cockpit Pay Code Explorer and identify the smallest functional parity steps that keep Cockpit read-only.

## Legacy `/x/pay-codes` functional surface

Current owner:

```text
PayCodeIndexPageController
resources/js/pages/x-change/pay-codes/Index.vue
```

Legacy index behavior:

- Loads Pay Codes from supplied Inertia props or from the existing vouchers API.
- Searches locally across:
  - code
  - mobile
  - account number
  - bank code
  - status
  - formatted amount
  - amount
- Filters locally by status:
  - all
  - awaiting_approval
  - active
  - redeemed
  - expired
  - pending
  - failed
- Infers display status from:
  - `display_status`
  - approval requirement
  - `status`
  - `redeemed_at`
  - expired `expires_at`
  - fallback `active`
- Presents stats:
  - total
  - active
  - redeemed
  - expired
- Provides legacy actions:
  - create Pay Code
  - show Pay Code
  - claim Pay Code
  - approval Pay Code

## Cockpit `/x/cockpit/pay-codes` current surface

Current owner:

```text
CockpitPayCodeExplorerPageController
CockpitReadOnlyPageProps
VoucherLifecycleCockpitReadModelProvider::forPayCodeList()
resources/js/cockpit/pages/PayCodeExplorer.vue
```

Current Cockpit Explorer behavior:

- Hydrates sanitized list records from `VoucherAccessContract::list()`.
- Accepts `activity_code` as a read-only query bridge from Operator Issuance Activity cards.
- Renders sanitized rows only:
  - code
  - template
  - amount
  - currency
  - status
  - display status
  - owner
  - last activity
- Renders read-only row action placeholders.
- Excludes unsafe payloads.
- Does not call provider APIs, mutate vouchers, execute drivers, move money, write journal entries, execute actions, send feedback, or mutate campaigns.

## Parity gap

Cockpit Explorer does not yet expose the legacy index's functional filtering and summary shape:

- no explicit `search` query separate from activity-code navigation
- no `status` query
- no legacy-compatible status inference
- no read-model stats
- no status filter option metadata
- no operator-visible active-filter summary
- no browser acceptance for the functional filter parity path

## Authorized Wave 30 scope

Wave 30 should add read-only functional parity only:

1. Add a Cockpit Pay Code Explorer filter contract for `search` and `status`.
2. Add read-model stats and filter-option metadata.
3. Apply legacy-compatible search/status filtering in the read-model provider.
4. Accept `search` and `status` query params in the Cockpit Explorer controller.
5. Render operator-visible filter controls as GET navigation, not mutation.
6. Browser-verify the read-only filter path.

## Explicit non-goals

Wave 30 must not:

- replace `/x/pay-codes`
- change legacy Pay Code index behavior
- change voucher generation
- change voucher redemption
- change claim UX
- mutate vouchers
- execute drivers
- call providers
- reserve, debit, or move money
- write journal entries
- execute x-action actions
- send x-feedback deliveries
- mutate campaign state
- expose raw payloads, provider payloads, wallet internals, account numbers, OTPs, recipient secrets, or funding sources

## Next slice

```text
Cockpit Wave 30B — Pay Code Explorer Filter / Summary Read Model Contract
```
