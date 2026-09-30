# Affiliation Networking

X-Change can maintain application-scoped sponsorship lineage through
`3neti/x-affiliation`. The graph records who sponsored whom; it does not grant
roles, move money, calculate commissions, or disclose personal information.

## Configuration

Affiliation is disabled unless the host explicitly configures:

```dotenv
XCHANGE_AFFILIATION_ENABLED=true
XCHANGE_INSTANCE_ID=<stable-installation-identity>
X_AFFILIATION_IDENTITY_PEPPER=<stable-high-entropy-secret>
```

`XCHANGE_INSTANCE_ID` identifies the logical installation, not a hostname,
deployment, or compute replica. The identity pepper derives network-scoped
mobile HMACs and must be preserved through normal backup and recovery.

`x-change:install` creates or resolves one stable application-instance network.
`x-change:doctor --strict` fails closed when enabled configuration or the
commissioned network is missing.

## Root adoption

An existing verified Account becomes a root only through explicit adoption:

```shell
php artisan x-change:affiliation:enroll-root <account-id> \
  --authorization-reference=<governed-reference> \
  --confirm-root-adoption
```

Ordinary sign-in and invitation creation never silently enroll a root.

## Sponsorship-bearing onboarding

Only targeted onboarding may establish sponsorship in the initial release.
Bearer onboarding remains unchanged and creates no affiliation relationship.

The authoritative sequence is:

1. Validate that the sponsor is already an active network member.
2. Normalize the intended recipient mobile and derive its opaque identity key.
3. Create, submit, independently approve, and offer an
   `x-provisioning::AccountInvitation` snapshot.
4. Link the offer to the onboarding Pay Code through the encrypted local
   authority bridge. The raw claim token is never voucher metadata.
5. After OTP-backed onboarding resolves the canonical Account, accept and
   activate the offer inside the same transaction as Pay Code completion.
6. Re-check both the canonical Account and verified mobile, then establish the
   sponsorship through `x-affiliation`.
7. If onboarding settlement fails, the offer acceptance, membership,
   sponsorship, and path projection all roll back.

The same authority replay is idempotent. A different invitation for an already
enrolled Account or mobile fails closed. The runtime exposes a generic
ineligibility result and never reveals the existing sponsor or lineage.

## Privacy and recovery

- Raw mobile numbers are not stored in affiliation records, provisioning
  snapshots, or affiliation journal facts.
- Changing a mobile identity or sponsor requires a future governed recovery or
  supersession profile; it is never inferred during onboarding.
- Direct sponsorships are authoritative. Ancestor/descendant paths are a
  rebuildable projection.
- Affiliation events are projected to x-journal with opaque references and
  facts hashes only.
- Affiliation alone has no financial, authorization, mandate, or API-scope
  effect.

Operational package details remain in `3neti/x-affiliation`.
