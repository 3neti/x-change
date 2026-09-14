# Pricing Baseline and Maintenance

## Purpose

x-change ships with a package-defined baseline Commercial Offering. This
baseline gives every host a known starting price list and waterfall during
commissioning.

A host may operate on this immutable baseline immediately after commissioning.
Any institution-specific pricing or waterfall change requires governed
maintenance by an onboarded Maker and a distinct Checker.

## Source of truth

The effective runtime price comes from the active governed Commercial Offering.

The package baseline is seeded from x-change configuration and captured into a
versioned Commercial Offering snapshot. Once an issuance operation accepts a
price, that Pay Code or Commercial Sale keeps the accepted offering version,
snapshot, and hash. Later price changes do not rewrite prior Pay Codes, sales,
allocations, Treasury postings, or journal records.

The current package baseline values are defined in `config/x-change.php` under
`pricelist`.

## Current package baseline prices

All prices are in Philippine pesos.

| Key | Label | Price |
| --- | --- | ---: |
| `cash.amount` | Transaction Fee | ₱15.00 |
| `voucher_type.payable` | Payable Voucher | ₱5.00 |
| `voucher_type.settlement` | Settlement Voucher | ₱8.00 |
| `inputs.fields.kyc` | KYC Verification | ₱18.00 |
| `inputs.fields.otp` | OTP Verification | ₱2.00 |
| `feedback.email` | Email Notification | ₱1.50 |
| `feedback.mobile` | SMS Notification | ₱1.20 |
| `feedback.webhook` | Webhook Notification | ₱0.50 |
| `inputs.fields.selfie` | Selfie Photo | ₱3.00 |
| `inputs.fields.signature` | Digital Signature | ₱1.50 |
| `inputs.fields.location` | GPS Location | ₱1.00 |
| `inputs.fields.email` | Email Address | ₱0.50 |
| `inputs.fields.mobile` | Mobile Number | ₱0.50 |
| `inputs.fields.name` | Full Name | ₱0.30 |
| `inputs.fields.address` | Full Address | ₱0.50 |
| `inputs.fields.birth_date` | Birth Date | ₱0.30 |
| `inputs.fields.gross_monthly_income` | Monthly Income | ₱0.30 |
| `inputs.fields.reference_code` | Reference Code | ₱0.30 |
| `cash.validation.secret` | Secret Code | ₱0.50 |
| `cash.validation.mobile` | Mobile Restriction | ₱0.50 |
| `validation.time` | Time Window Validation | ₱0.80 |
| `validation.location` | Location Validation | ₱1.20 |
| `cash.validation.payable` | Vendor Alias / B2B Restriction | ₱2.00 |
| `rider.message` | Rider Message | ₱2.00 |
| `rider.splash` | Rider Splash Screen | ₱20.00 |
| `rider.url` | Rider Redirect URL | ₱50.00 |
| `cash.validation.location` | Location String / Legacy | ₱0.00 |
| `cash.validation.radius` | Radius String / Legacy | ₱0.00 |

## Pricing rationale

The baseline follows these principles:

- base transfer pricing is cost-recovery oriented;
- third-party API features are priced cost-plus;
- text fields are priced minimally;
- media and location fields are priced by storage or API cost;
- Rider features are value-priced because they create marketing and conversion
  surface; and
- enterprise voucher types remain accessible but distinct from ordinary
  disbursement.

## Maintenance rule

Pricing maintenance requires:

- an onboarded Maker;
- a distinct onboarded Checker;
- explicit commercial capability grants;
- a submitted immutable revision;
- independent approval and publication; and
- explicit activation.

The required Commercial capabilities are typically:

- `commercial.offerings.manage`
- `commercial.offerings.approve`

The System Principal cannot receive these authorities, and the same human
cannot hold both sides of this maker/checker pair.

## Update workflow

1. Maker signs in.
2. Maker opens **Commercial Controls**.
3. Maker selects the governed profile, such as `pay_code` or
   `account_funding`.
4. Maker drafts a new Commercial Offering version.
5. Maker edits the price list and/or waterfall.
6. Maker submits the immutable draft.
7. Checker signs in separately.
8. Checker reviews price list, waterfall, legal trace, effective time, and
   snapshot hash.
9. Checker approves and publishes.
10. A controlled activation makes the version effective for future issuance.
11. Existing Pay Codes and Commercial Sales retain their original pricing
    snapshot.

Submission does not change live pricing. Publication makes the reviewed version
eligible. Activation is the controlled instant when new issuance starts using
the new snapshot.

## CLI authority setup

Authorize the Commercial Maker:

```bash
php artisan x-change:commercial:authorize-operator maker@example.test \
  --column=email \
  --capability=commercial.offerings.manage \
  --authorization-reference=board-resolution:commercial-maker
```

Authorize the Commercial Checker:

```bash
php artisan x-change:commercial:authorize-operator checker@example.test \
  --column=email \
  --capability=commercial.offerings.approve \
  --authorization-reference=board-resolution:commercial-checker
```

Use identities and authorization references that match the institution's actual
delegation records.

## Verification

Before and after a pricing change, inspect:

```bash
php artisan x-change:commercial:governance-status --json
php artisan x-change:doctor --commercial-governance --strict --json
```

Then issue a controlled non-live acceptance Pay Code and confirm the Commercial
Sale captured the intended offering version and snapshot hash.

## Rule of thumb

Commissioning can finish without onboarded Maker/Checker humans.

Pricing maintenance cannot begin without them.
