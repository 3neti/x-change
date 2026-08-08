# Commercial Governance Commissioning

Normal installation uses the package-owned baseline catalog and waterfall. DevOps does not enter prices and does not create fake operator accounts.

```bash
php artisan x-change:install --force --no-interaction
php artisan x-change:commercial:governance-status --json
php artisan x-change:doctor --strict --no-interaction
```

The protected commissioning checklist shows:

- governance mode and state;
- active `pay_code` and `account_funding` profiles;
- baseline or maker-checker origin;
- package version and activation time;
- whether issuance is available;
- whether price changes are locked;
- maker and checker readiness counts.
- Partner registry readiness and pending Partner or destination approvals;
- the live provider-payout gate, scheduled reconciliation, and owning queue;
- open provider-cost review and commission-payout counts.

`x-change:doctor --strict` treats an active immutable baseline as operational. This means the system may issue using unchanged package pricing. Before any price change, use the stronger control gate:

```bash
php artisan x-change:doctor --commercial-governance --strict --json
```

That command fails until independent maker and checker authority exists.

Authorize named operators with stable institutional references:

```bash
php artisan x-change:commercial:authorize-operator maker@example.test \
  --column=email \
  --capability=commercial.offerings.manage \
  --authorization-reference=board-resolution:commercial-maker

php artisan x-change:commercial:authorize-operator checker@example.test \
  --column=email \
  --capability=commercial.offerings.approve \
  --authorization-reference=board-resolution:commercial-checker
```

The commands reject the System Principal, combined maker-checker authority, unknown capabilities, and missing identities. Credentials are not printed in commissioning or governance status.

Partner and Commercial Operations capabilities are granted independently. A bank may reuse the same organizational maker and checker roles, but one person must never approve their own Partner, destination, Offering, or payout request.

```bash
php artisan x-change:commercial:authorize-operator maker@example.test \
  --column=email \
  --capability=commercial.partners.manage \
  --authorization-reference=delegated-authority:partner-maker

php artisan x-change:commercial:authorize-operator checker@example.test \
  --column=email \
  --capability=commercial.partners.approve \
  --authorization-reference=delegated-authority:partner-checker

php artisan x-change:commercial:authorize-operator maker@example.test \
  --column=email \
  --capability=commercial.commissions.request \
  --authorization-reference=delegated-authority:commission-maker

php artisan x-change:commercial:authorize-operator checker@example.test \
  --column=email \
  --capability=commercial.commissions.approve \
  --authorization-reference=delegated-authority:commission-checker
```

Keep `XCHANGE_COMMERCIAL_LIVE_PROVIDER_CALLS_ENABLED=false` while configuring and reviewing the workspace. Enabling it does not send anything automatically: an authorized operator must still submit an independently approved batch. Provider status checks run on the configured Commercial Operations queue, normally `x-change-funding`.

Changing the governance mode changes the commissioning fingerprint. Re-run strict checks and deliberately adopt the updated manifest through the documented commissioning recovery flow; do not edit the manifest table.
