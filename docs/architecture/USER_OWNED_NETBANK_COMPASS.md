# User-owned NetBank accounts compass

## Current DIRECT reserve decision (2026-11-22)

NetBank support confirmed that the existing shared API credentials are authorized to initiate DIRECT transfers between the nominated NetBank corporate accounts and reported no minimum. This is provider guidance, not evidence of a completed DIRECT transfer. Its amount response (omit the decimal point) does not explicitly choose between `10` and `1000` for PHP 10.00, and its answer did not explicitly address fees. By user decision, proceed on the working assumptions of integer PHP minor units (`1000` for PHP 10.00) and no DIRECT fee for these accounts; UAT is deferred. After fixture-backed tests pass, use one specifically authorized nominal live transfer to check the actual amount and fee. Resolve uncertainty before submission if the assumed encoding could exceed the authorized peso amount. If provider evidence differs or is ambiguous, stop and revise the policy.

The old gate below is superseded as an implementation gate: provider-neutral contracts and fixture-backed implementation may proceed. UAT is deferred. The gate still prevents enabling reserve-funded issuance until request units, fees, entitlement, status retrieval, idempotent timeout recovery and source-to-destination settlement have been verified. After the fixture tests pass, one specifically authorized nominal live transfer may be used to establish provider behavior and reconcile the source debit, destination credit, fee, treasury and journal. Do not make additional transfers merely to test duplicate handling. Any live transfer requires authorization covering its amount, source and destination.

For applicable newly issued disbursement obligations, the intended flow is selected personal account → confirmed DIRECT transfer to a platform-controlled reserve → separately attributable owner and Pay Code obligation → activation → payout from the frozen reserve. Calculate fees and principal separately; a payable collection does not automatically create a disbursement reserve obligation. Record the transfer identity and idempotency key before submission. A pending or uncertain transfer cannot activate a Pay Code or justify a second transfer. A settled transfer survives issuance failure for safe recovery. Existing issued Pay Codes keep their frozen routes and are not migrated implicitly.

The shared bank account is a platform reserve in this technical design; legal escrow status and account arrangements are not established by this document. Distinguish the provider bank balance, internal Client Funds and reserved obligations. External deposits and surplus reconciliation remain separate work; a balance delta alone does not authorize credit. Release compatible EMI packages before x-change, validate the host in testing, and obtain explicit payout deployment approval.

The local integrated suite completed with 4,919 passed and 23 skipped against the local EMI changes; that result does not test a DIRECT reserve transfer or establish production readiness. Historical entries below describe conditions at the time recorded and must not be read as current rollout status.

## Authority and invariants

- x-change owns account selection, user-visible account details, internal client-funds accounting and transaction snapshots. NetBank integration owns provider-specific requests and reports provider capabilities; onboarding owns identity and ownership evidence.
- Each normalized personal bank account belongs to at most one user. The platform's shared bank account is owned by the platform and is never represented as a user's personal account.
- NetBank accounts are assigned by an authorized administrator; separate ownership verification is not required for this version. Assignment is not evidence that an account can fund payouts. Each capability is independently ready or unavailable.
- A personal account is selected by default only after administrator assignment and operation-specific readiness checks. An unavailable selected account fails closed; no silent shared-account fallback is allowed.
- A transaction retains the account and routing selected at its creation. An account change cannot redirect an existing transaction.
- Client funds are an internal ledger position, not a synonym for a bank-reported balance. Never spend funds attributable to another account or credit the same provider transaction twice.
- Full account details are visible only to authorized users and excluded from public responses and unnecessary logs. Never persist API secrets in documentation, logs or account metadata.

## Evidence and acceptance

- The second NetBank account's alias and an incoming PHP 33 transaction were previously confirmed using provider evidence. The user subsequently reported a settled PHP 55 InstaPay payout from the second account on 2026-11-21 using the configured shared credentials, supported by a NetBank transaction record and recipient receipt. This demonstrates that payout path under the configuration used; it does not establish per-transaction account selection or an internal client-funds credit.
- The sandbox lifecycle scenario must use provider fixtures for financial movements, capture before/after internal and provider wallet balances, and verify isolation, snapshots, replay safety and failure behavior.
- Provider checks for the existing transaction and bank balance are read-only. No new live transfer or manual ledger credit is part of local acceptance.
- Record tests, limitations, actual account capabilities and reconciliation status before describing this feature as ready. Do not deploy to Cloud under this plan.

## Gate: NetBank transfer to Pay Code reserve

**Status: NOT PASSED.** Reserve-funded issuance and external-deposit synchronization are deferred TODOs: do not implement or activate them until the transfer and confirmation gate is satisfied and its evidence reviewed. Continue the provider-neutral banking interface, account assignment, readiness, account-specific read-only balance inquiry, and fixture-backed tests independently. Disbursement from a selected personal account stays unavailable until its provider capability is confirmed.

Before closing this gate, establish with NetBank and controlled evidence: (1) the shared API credentials are authorized to send from the second corporate account to the nominated platform reserve account; (2) the supported transfer method, account identifiers, fees, limits and timing; (3) the exact meaning of initial, pending, final-success and failure statuses and authoritative evidence of receipt; (4) transaction lookup, callback delivery, retry and duplicate-reference semantics, including timeouts and uncertain outcomes; and (5) matching source debit to destination credit with immutable provider identifiers. A real transfer test, if needed, requires separate explicit authorization with amount and destination; the present authorization permits no new live transfer. Never publish credentials, raw payloads or sensitive account data.

The integration can construct a disbursement request containing source account and customer ID, returns a transaction ID and status when supplied, and offers status lookup. This is code evidence only: it does not demonstrate actual transfer entitlement, final settlement, transfer method or idempotency guarantees. An accepted response is not confirmation of receipt.

A read-only balance inquiry on 2026-11-21 returned PHP 83.00 available for the second account; the provider response's `as_of` field was 2026-11-06 UTC and must not be described as proof of a current balance. The earlier PHP 33 VCA deposit shows incoming routing only, not inter-account transfer or an internal client-funds credit.

## Proposed behavior after the gate

- Transfer the calculator's complete issuance requirement from the selected user's NetBank account into the platform-controlled Pay Code reserve; issue or activate only after authoritative funding confirmation. Account for fees under the applicable policy, separately from the voucher obligation.
- Pending, failure or uncertain transfer outcomes remain pending or require reconciliation; never blindly retry or ask for a second payment. Confirmed funding remains held through issuance failure and idempotent recovery.
- Maintain reconciled per-account and per-voucher obligations, and prevent reserve assets from being counted twice. Prefer a dedicated reserve bank account if feasible; the legal status and account arrangements require confirmation.
- Observe direct deposits and withdrawals in the user's bank account through balance refresh and provider transaction evidence. A balance observation alone does not create a ledger credit or move funds to the reserve. Process identified transactions idempotently and flag unmatched evidence.
- Notify users about detected deposits, low balances, pending or failed reserve funding and reconciliation exceptions. No external notification is sent without the appropriate configured pipeline.

## InstaPay evidence (2026-11-21)

The user supplied NetBank and recipient evidence for a settled PHP 55 API payout from the second account after setting that account as the global source. The NetBank record and recipient receipt agree on amount, source and time. The user subsequently restored the original host configuration. This is evidence of that live InstaPay flow, not proof of per-transaction account routing, DIRECT reserve transfer support, or external-deposit synchronization. Do not repeat the transfer to validate this evidence.

## Dedicated funding references

NetBank evidence shows that the earlier PHP 33 deposit used a twelve-digit VCA, while the PHP 99 deposit used a sixteen-digit VCA. The latter therefore cannot collide with the proposed twelve-digit allocator. The local sandbox contains existing sixteen- and twenty-one-digit addresses under the same alias; the PHP 99 VCA matches its recorded standing address, but the PHP 33 VCA is absent from local funding addresses. Neither supplied transaction ID has a matching local provider funding observation or wallet transaction metadata entry. This is not proof that no credit occurred through some other path; reconcile the transaction before crediting it. The user confirmed that the sandbox will be the only writer of seven-digit references during the planned test.

The fixture-backed allocator retains an encrypted reservation per intent and can record historical twelve-digit addresses so they are never reassigned. This is not proof that every address ever generated outside the sandbox has been identified. Dedicated NetBank funding stays disabled until the historical namespace is reconciled, known prior addresses are recorded, and the account-specific flow and operational checks are complete. Never manually credit the bank transactions on the basis of this evidence alone.

The PHP 99 standing address is marked shared while its frozen destination is the second account, not the current shared account. Settlement must fail closed and put matching evidence in suspense until an authorized reconciliation corrects the binding. Fixture tests cover this mismatch for standing receipts and one-time funding intents. No existing address or prior ledger entry was changed by this check.

## Reconciliation and key rotation limitations

The existing standing-address binding migration is a prospective Client Funds cutover. It creates a future-effective revision; it is not an operator workflow for correcting the original bank-account classification or proving that the earlier PHP 99 receipt has not already been credited. Do not use it as a substitute for reconciling that receipt. The legacy mismatch remains blocked and evidence must be traced through the existing provider and ledger records before an authorized correction can be designed and applied.

Account identity fingerprints and the identity-key guard depend on the application key. The internal identity rotation action can atomically re-key recoverable NetBank identities and backfill legacy links, provided the old key remains configured as a Laravel previous key and account records can be decrypted. It fails closed on orphan identities, duplicate records, mismatched owners, or unavailable keys. Its tests cover the identity slice only; it is not an operator-facing procedure or a complete application-key rotation.

Do not rotate APP_KEY yet: funding references, payer identity hashes, broadcasting, and other application data may also depend on it. Before release, inventory every dependent key and implement a backed-up, rehearsed system-wide rotation and recovery procedure. Preserve previous encryption keys until all encrypted data is independently migrated and verified. Never delete the identity guard or reset identities to bypass a mismatch.

## Alias assignment preflight

The local integration checks that a five-digit NetBank alias is not assigned to a different personal account or the platform account. Funding instructions and settlement also require the alias frozen in the destination snapshot to match the assigned account; a missing or mismatched alias fails closed. The check covers new assignments and the legacy funding-settings action, and serializes these managed writes using the identity guard row. The read-only preflight reports duplicate aliases as counts without returning account numbers or aliases. A matching legacy link for the same account is permitted. This checks recorded assignments only; it does not establish the completeness of historical provider-issued VCAs or authorize funding.

## Local funding readiness

Dedicated NetBank funding is disabled by default. The `XCHANGE_DEDICATED_NETBANK_VCA_ENABLED` setting gates one-time funding and its administrator setup; standing addresses remain separately gated by `XCHANGE_DEDICATED_NETBANK_STANDING_ENABLED`. An authorized administrator may mark an assigned account funding-ready only after confirming that prior VCA references have been reviewed and recorded, the account and alias match its provider link, and an isolated Treasury connection exists. The setup records the administrator and emits the configured audit event; repeating it is idempotent. Funding readiness is independent of disbursement readiness.

Neither switch is enabled in the local sandbox by this work. The reviewed-history confirmation does not itself discover historical VCAs, prove ownership at NetBank, reconcile the PHP 99 receipt, or create a ledger credit.

## Local sandbox commissioning (2026-11-21)

An authorized System Treasury session assigned the second account to its existing sandbox user. The account was bound to a separate local-only provider inventory and settlement resource without crediting Client Funds or moving money. Both funding and disbursement remain unready; the one-time and standing-funding switches remain off. A read-only balance inquiry using normalized digits returned unavailable; the same inquiry using the provider-formatted account number returned a stale-balance result. Assignment now preserves the provider format separately from the normalized identity; the sandbox record was updated through the identity-preserving action. Do not treat the stale provider response as current liquidity or bypass the readiness check.

The local account reconciliation preflight was rerun after assignment: one assigned account and one dedicated link, with zero duplicate accounts, duplicate aliases, or unreadable records. This check does not establish provider readiness or reconcile historic funding evidence.

Both guarded retry and corrected-destination recovery must compare the reconciliation source with the Pay Code’s frozen account and current treasury binding before contacting a provider. A missing or inconsistent personal-account snapshot fails closed. Legacy shared-account recoveries remain supported.

The account-specific funding-to-redemption integration fixture also exercises a pending provider response followed by successful reconciliation. It checks that a repeated reconciliation does not resubmit the payout and that personal inventory is reduced only upon confirmed settlement. This Pest scenario is not yet an operator-runnable sandbox lifecycle scenario.

The VCA allocator checks address fingerprints made with retained application keys and the configured `XCHANGE_FUNDING_PREVIOUS_REFERENCE_HASH_KEYS`, as well as the current funding reference key. Keep prior reference keys in the host secret store while historical addresses remain relevant; never place their values in documentation or logs. This protects VCA allocation from reuse only and does not migrate other APP_KEY-dependent data or establish a full application-key rotation procedure.

### Application-key rotation release gate

The identity re-key action is not a general rotation command. An operator must not rotate the host's application key until a backed-up rehearsal on an isolated copy has passed all of the following:

1. Inventory encrypted fields, cookies, queue payloads, and signed or time-limited links across the host and its installed packages; retain previous encryption keys until their data has been re-encrypted or expired and verified.
2. Inventory persisted keyed fingerprints and idempotency references. In x-change these include funding and payment references, payer and mobile-verification hashes, stored-value holder authority, claim evidence, and recovery references. Establish and test their individual migration or backward-compatible verification before changing the key.
3. Preserve the funding reference key separately before rotation where applicable, and retain all earlier reference keys required to identify old addresses. The allocator's previous-key lookup only prevents VCA reuse; it does not restore every historical lookup.
4. Pause funding, issuance, settlement, and recovery workers during migration. Capture and verify restorable backups. Never log or copy key values into task output, issue trackers, or source control.
5. Run duplicate-account and unreadable-record preflight, migrate identity fingerprints and their guard atomically, then verify the same preflight, historical-reference matching, pending payment reconciliation, stored-value authority, payout retries, and journal continuity. Exercise restoration as well as the forward migration.
6. Resume workers only after every dependent host and package passes its own checks. If any step is unverified, restore the prior configuration and data together; do not remove the identity guard to force startup.

This gate is open work, not authorization to rotate a live key. Third-party packages and host-specific key consumers require their own inventory and tests.

NetBank's published Retrieve Bank Account Details example includes the account and available balance but does not document a balance observation timestamp. The current adapter maps `created_date` to `as_of`; that mapping must not be presumed to represent a current balance without NetBank confirmation. A successful request's receipt time and the provider's balance-as-of time are different facts. Keep the freshness gate closed until the provider confirms freshness semantics or supplies a trustworthy timestamp; do not replace the timestamp with the local clock merely to make readiness pass. Reference: https://virtual.netbank.ph/docs

### Read-only provider history review (2026-11-21)

A bounded account-history request beginning before the provider-reported account creation date returned five settled transactions through 2026-11-21: four credits and one debit. Two credits used the previously recorded twelve-digit VCA, one used the previously recorded sixteen-digit standing VCA, and one used the unformatted corporate account number. The local reservation and standing-address records for the two known VCAs were verified read-only. A direct history query for the twelve-digit VCA returned two transactions and the sixteen-digit VCA returned one. No transaction or provider payload was modified or persisted by this review.

This establishes the references observed in provider transaction history for the requested period, not the full set of unused VCAs or previously shared QR codes: NetBank documents that unrestricted VCAs need not be registered, and its account-level VCA listing covers only VCAs with limits. The first broad date query returned HTTP 400; a shorter range beginning before account creation succeeded. The previously identified standing-address ownership mismatch and funding ledger reconciliation remain unresolved. Do not enable dedicated funding merely because the observed references were found.

The three provider transactions observed under the two known VCAs have no matching provider funding observation or funding intent by transaction ID in the sandbox. This is a limited lookup and is not proof that no other ledger path credited them. No credit or historical correction was made.

The local sandbox dependency inventory also found direct APP_KEY consumers outside x-change: x-campaign derives worksheet manifest hashes from the application key; emi-netbank defaults its funding reference key to APP_KEY; and the sandbox host derives Pipedream external-user identifiers from APP_KEY. Retaining Laravel previous encryption keys alone does not preserve these derived identifiers. Review and migrate each consumer in its owning package or host before declaring a full host rotation safe. This is an inventory finding, not a completed cross-package audit.

A subsequent read-only trace found the active sixteen-digit standing address still classified as shared while its frozen bank account is the personal account. It has one binding revision, one QR artifact, and no local receipt records. For the three transfers on the two known VCAs, searches found no corresponding funding evidence claim, standing receipt transaction key, or wallet-transaction metadata reference. These are bounded direct-identifier checks, not proof that no indirect credit exists. Do not mutate the historical binding or credit balances until the original ledger attribution is established and an appropriate audited correction exists.

For the same three provider transfers, a read-only search found no same-amount wallet deposit within one day of each provider timestamp. The provider amount fields were present as positive PHP minor-unit integers. This time-window and amount comparison is only corroborating evidence: an indirect or differently timed credit can still exist, so it does not authorize credit or a retrospective binding rewrite.

A fixture-backed regression test now blocks reopening a shared NetBank QR when its frozen bank account or alias differs from the currently selected shared destination, before any provider request. Formatting-only account-number changes remain compatible. In the actual local record, a rollback-only check with outbound HTTP blocked was stopped earlier by the existing different-ledger binding guard; this confirms the address was not reopened, but does not correct its historical binding or determine past credit.

A later read-only migration preview returned `safe: false` because `binding_requires_migration` was false; it reported zero receipts and no locally recorded unattributed observations. This preview evaluates ledger binding, not the bank destination mismatch or provider transactions absent from local observations. It must not be used as evidence that the historical bank destination or deposit attribution is corrected.

The user confirmed an externally generated QR Ph from the earlier second-account test. A local decode of that test artifact matched the previously observed twelve-digit VCA, which is already recorded as a historical reservation. This confirms that particular QR's reference; it does not establish whether additional unused codes were generated or shared. The QR payload was not copied into this document.

A read-only search of the local journal's payload, references, metadata, and source-event identifiers found no literal transaction-ID matches for the three known VCA transfers. Hashed, indirect, external, or differently referenced records are outside that search; the result is not proof that no credit occurred.

The user confirmed that the externally generated test QR was the only outside-generated twelve-digit VCA/QR under this alias, and supplied the three associated provider transaction records. Two settled deposits used the reserved twelve-digit test VCA; one used the existing sixteen-digit standing VCA. This confirmation resolves the question about other externally generated twelve-digit test codes, but does not by itself prove ledger attribution or correct the sixteen-digit standing-address binding.

### Standing address deposit timing review

The sandbox's sixteen-digit standing address was created and activated at 2026-11-21 00:16:10 UTC, with its sole binding revision effective at the same time. The provider's PHP 99 deposit occurred at 00:16:44 UTC, 34 seconds later. The address was therefore active for the deposit; pre-activation filtering does not explain the absent local receipt. A further read-only check found no wallet deposit of exactly PHP 99 anywhere in the sandbox, without a date restriction. Indirect, aggregated, or net-of-fee credits remain unproven. No provider sync, credit, or binding mutation was performed.

The existing standing-address sync is not a read-only reconciliation preview: it can record observations and receipts and settle eligible deposits. Do not run it against this address as a diagnostic while the frozen destination and ledger binding are unresolved. The existing ledger binding migration does not correct the historical bank-destination classification; the original revision must remain intact until a suitable audited correction is designed and its effect on historic deposits established.

A broader review of sandbox wallet deposits around the payment found a separate PHP 4,000 verified NetBank funding credit to the same owner's treasury position eight seconds after the PHP 99 provider transaction. Its linked settled provider observation is for PHP 4,000 and carries a different provider transaction ID from all three reviewed transfers. Its timing alone is not evidence that it includes the PHP 99, and the records inspected do not establish such a relationship. Other nearby deposits correspond to holds, issuance, fees, or projections; none equals PHP 99. This is corroborating evidence, not authorization to credit or reclassify the historical deposit.

### Fresh funding QR provider review (2026-11-21)

The local fresh one-time funding intent created for this review retained the selected personal account and isolated Treasury connection, but instruction provisioning did not complete. Its recorded thirty-minute expiry subsequently passed; do not issue instructions for it by rewriting its expiry or frozen destination. Its VCA remains reserved. The earlier separately attempted alias-token diagnostic returned HTTP 500; it did not establish the failure step or provider-side effects of the original request. NetBank's documentation warns that a successful alias-token generation replaces the previous token; avoid further token-generation retries until its operational impact and provider state are established.

Read-only VCA history and limits lookups using the stored digits-only corporate account returned HTTP 400. The same endpoints with the account's recorded dashed format returned HTTP 200: no transactions or limits were returned, and no explicit pre-transaction validation status was included. This does not prove the VCA was never registered or that an ambiguous earlier operation had no effect. No QR or payment was created and no balance was credited.

Future assigned NetBank funding destinations now preserve the user-recorded provider account format after checking it and the provider link against the normalized account identity. This does not change any existing intent snapshot or establish that the alias-token HTTP 500 was caused by formatting. The provider's required VCA write-account format and alias-token behavior need confirmation before retry or a fresh request.

### Direct QR mode: implementation gate

The NetBank adapter can render a direct amount-encoded QR using a supplied, reserved seven-digit dedicated-account reference. It records that NetBank registration and provider-enforced amount, one-time use, and expiry were not requested. This capability is additive and can be selected by x-change for account funding when the direct-QR gate is enabled.

Do not enable the proposed direct mode for personal account funding until account-specific mode selection is frozen into the funding instruction and authoritative evidence is monitored for wrong-amount payments, duplicate payments, and payments arriving after expiry or after settlement. Direct QR monitoring is now covered by the local fixture tests described below; operational provider history and worker monitoring still need commissioning. Tests must cover each case without provider calls or automatic duplicate credits. Keep the prior expired request and its VCA reservation intact.

The account funding destination now freezes the selected account's instruction mode, and the instruction action can dispatch the dedicated reserved address to the provider's direct-QR capability. Unknown modes fail closed. The separate direct-QR gate is deliberately hard-disabled: no administrator action or environment setting can enable this path until the exception-monitoring work above is implemented and verified. Existing accounts default to the existing registered-VCA behavior; no account settings or historical funding records were changed.


### Direct QR exception monitoring (local fixtures)

Issued dedicated direct-QR funding requests carry a scheduler marker in their frozen record. The funding scheduler continues to inspect their expired, cancelled, settled, suspense and reversed states without a time cutoff. The worker verifies each provider transaction against the frozen account and VCA. A further verified payment discovered before the request expires can credit the assigned owner’s Client Funds once through a separate funding settlement. Late payments and payments without a valid assigned-account Treasury binding remain in review. Repeated observations of the same provider transaction do not open repeated cases. A closed intent is checked only if its encrypted destination confirms the dedicated direct-QR mode. Failed scans remain visible as job failures and record their failed check, so a failing request does not indefinitely monopolize the scheduler batch. The original settled payment retains its separate reversal check.

Fixture tests cover the scheduler, missing provider transaction dates, late and duplicate payments, unchanged settlement, and funding through issuance and pending payout reconciliation on the frozen personal Treasury connection. These are local fixture results, not proof of NetBank transaction-history retention, availability, or operational monitoring in a deployed environment. The direct-QR gate remains disabled. Before commissioning, verify the scheduled worker and failure alerting are active, confirm sufficient provider-history retention and pagination for the monitoring period, and verify an unpaid newly generated QR's encoded amount and destination. Do not reuse the expired request or its reserved VCA.


### Assigned-account funding fallback

For a fresh Account Funding intent bound to a valid user-owned NetBank account and its frozen dedicated Treasury connection, the verified amount received is credited to the account owner’s Client Funds even when the requested amount or payer mobile differs. The settlement records the account attribution policy, requested-amount match and available payer-identity match as notes. Shared-account and on-demand issuance attribution rules continue unchanged. A further transaction on an already-settled direct QR is credited separately before expiry and deduplicated by the provider transaction. Previously opened review cases and late payments remain in the existing manual reconciliation process.

This slice covers Account Funding. It does not yet provide the same fallback for payable voucher or other payment-order flows. Multiple direct-QR payments discovered before settlement are individually verified; the first can settle to Client Funds and the remaining on-time payments can settle independently on monitoring. The direct-QR rollout gate remains disabled; fixture results are not authorization to replay or settle historical review cases.


### Payable collection follow-up

A reviewed excess payment now preserves its frozen dedicated bank and Treasury binding when credited to the issuer. A shared payable payment with multiple configured NetBank Treasury connections uses its frozen shared account and configured shared Treasury, and rejects a mismatched bank account. Both paths have fixture coverage. Automatic assigned-account payable QR selection and fallback still require implementation; the direct-QR rollout gate remains disabled. No historical payment was credited by these changes.


## DIRECT commissioning evidence and next gates (2026-11-23)

NetBank confirmed that DIRECT amount `10000` is PHP 100.00, transfers between NetBank accounts have no charge under the commercial proposal, and idempotency keys must be 8–36 characters. The first trial was rejected at the API level for an overlong key and produced no transaction. A separately authorized request with a valid key settled as transaction 489665712; the owner supplied matching source disbursement and destination collection evidence for PHP 100. The source debit was also observed by API. This proves the provider transfer, not the integrated reserve-backed issuance lifecycle.

The commissioning transfer was submitted directly through the provider client and journaled, but did not automatically post the application Treasury movements. Reconcile only after establishing the correct source and destination inventories, existing accounting and unique provider evidence; do not create a Pay Code obligation or duplicate postings. Enforce the provider key limit without silently changing frozen keys on retries. Until complete fixture lifecycle, full suite, local worker, scheduler, readiness, recovery, source-freezing and accounting checks pass, keep reserve issuance gated and do not infer release readiness.


### DIRECT fee evidence discrepancy

Read-only retrieval of settled transaction 489665712 returned amount PHP 100.00 and a `fees` entry with amount PHP 100.00, despite NetBank support's no-charge confirmation and an observed source available-balance decrease of PHP 100.00. The meaning of the fee entry and any actual fee are unresolved; do not treat it as a confirmed charge or silently discard it. The reserve adapter currently sums fee entries and the settlement guard requires zero, so this evidence would be held for review. Obtain provider clarification or authoritative evidence and reconcile before enabling automated reserve issuance or posting the out-of-band commissioning transfer.
