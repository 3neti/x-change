# Stored-Value Collection and Compiled App Profiles

## Goal and sequence

This is one program with two ordered releases. First, authorize and complete stored-value collections without an OTP for newly issued Pay Codes. Second, compile all packaged app profiles and expose their journeys through the host's selected primary profile and additional profiles. Profile navigation never grants permission to move money.

## Collection release

- Freeze an explicit collection-authorization policy in newly issued stored-value voucher instructions. Existing vouchers retain their OTP collection path.
- Approved Partner API clients and authenticated staff may collect without holder OTP only within commissioned per-transaction and daily limits. Unconfigured limits deny this path. The operations checkout session retains OTP.
- Resolve the frozen instruction at start and recheck it at commit. Record the selected method and collector identity; never record OTP evidence for a collector-authorized spend.
- Support operator-entered amounts and a server-enforced fixed fare. Keep the marked-code parser, keyboard scanner, camera OCR, idempotency key, transaction lock, Treasury draw, receipt, and journal event.
- Prove duplicate, concurrent, insufficient-balance, expired, unauthorized, OTP-required, and ambiguous-retry behavior before enabling the new path.

## Profile release

- Compile package YAML into typed profile and journey records. Reject unknown keys, conflicting journey definitions, missing routes/adapters, and invalid homes during readiness checks.
- `XCHANGE_APP_PROFILE` selects the primary profile and its home. `XCHANGE_APP_PROFILE_ADDONS` supplies additional profile keys. Deduplicate shared journeys; the primary profile wins navigation ordering.
- Wire every packaged journey to a usable v1 adapter built on existing issuance, claim, campaign, collection, record, Form Flow, exception, and activity primitives. No journey is advertised with an inert placeholder.
- Keep direct authorization as the journey default. Maker-checker is an explicit action policy enforced by the backend; read-only journeys have no approval step.
- V1 money changing accepts an externally quoted payout amount and does not calculate FX. Lending records a loan reference and disburses without a loan book. Offshore remittance records origin evidence and delivers PHP through existing rails. Opt-in captures consent/enrollment without underwriting.

## Gates

Release x-change first. Test package Pest, frontend, lifecycle, browser, and Pint; adopt in local x-change-sandbox and x-PayOut only through Composer publication. Verify all twelve profiles and primary-plus-add-on composition in Cloud testing. A payout Cloud deployment requires separate explicit approval.

The [collection compass](COLLECTION_COMPASS.md) and [app-profile compass](APP_PROFILE_COMPASS.md) are the invariants for implementation and review.

## Commissioning contract

The collector path is closed by default. A host must set a positive per-collection limit and daily limit, enable staff collection, and grant the `x-change.stored-value.collect` Gate ability to a specifically approved authenticated operator. A Partner API client instead needs an active `stored-value:spend` scope and an active mandate with positive limits; production also needs an activated production mandate. The fixed fare setting is in minor currency units and applies at the server boundary to every collection channel. Operations checkout remains holder-OTP authorized.

Run `php artisan x-change:app-profiles:check --json --no-interaction` during provisioning. The selected `XCHANGE_APP_PROFILE` is the primary home; `XCHANGE_APP_PROFILE_ADDONS` is a comma-separated list. Profile navigation is informational and does not grant the collector Gate or Partner mandate.

## Implementation gate still open

The package compiles every profile and projects navigation, but route existence alone does not prove a journey's intake, approval, and result semantics. Payroll and ayuda use worksheet views; gaming payout now requires an event reference and seeds its voucher instruction. The gaming worksheet contract is published in x-campaign v1.1.4, and the x-change profile branch is based on v1.0.306. Direct worksheet authorization and explicit officer approval have separate tested paths. Loan, exchange, and local/offshore remittance issuance now collect bounded purpose evidence in Quick Generate and reject invalid or unavailable journeys at the HTTP boundary. Distribution and lead capture preselect the corresponding campaign usage when the profile journey opens the endpoint editor.

Collection activity and unfinished collection views now list only the authenticated collector's recent records and reopen a reference through the existing status and commit boundary. Product opt-in now has a separate public record flow: the issuer publishes a versioned consent statement, and each self-attested mobile may record one consent per version. The record freezes the statement hash and stores personal fields encrypted; it never implies underwriting or issues a Pay Code. Profile commissioning and publication remain behind this gate: prove specialized issuance result and activity projections, verify lead and digital-marketing frozen instructions, and run browser and lifecycle coverage across the distinct paths. Host example environment variables are configuration examples, not activation evidence.
