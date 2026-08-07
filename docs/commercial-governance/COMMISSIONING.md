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

Changing the governance mode changes the commissioning fingerprint. Re-run strict checks and deliberately adopt the updated manifest through the documented commissioning recovery flow; do not edit the manifest table.
