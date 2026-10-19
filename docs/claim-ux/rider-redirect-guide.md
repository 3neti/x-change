# Backend rider redirect integration

See the [plan](RIDER_REDIRECT_PLAN.md) and [compass](RIDER_REDIRECT_COMPASS.md).

## Activation

Release x-rider with RedirectDestinationPolicy (x-change requires ^1.1), and Form Flow with TerminalNavigation (^1.11), before releasing x-change. Install package versions, apply the new redirect migration, publish/build assets, and verify `x-change:rider:deliver-journal` appears in `schedule:list`. No host frontend edits are needed.

Enable `X_CHANGE_RIDER_REDIRECT_ENABLED=true` only after testing. Set `x-rider.redirects.allowed_hosts` explicitly; use `allow_any_host` only as a deliberate host policy. Invalid destinations fail before the shared issuance action resolves the issuer or charges. HTTP(S) destination validation is structural and policy-based; it performs no remote fetch and does not promise availability.

Only new issuance receives a frozen obligation. Preview vouchers create none. Existing obligations continue to work if new-issuance activation is disabled. Existing vouchers retain legacy behavior. Destinations are encrypted at rest; journal records contain host, destination fingerprint, obligation reference, voucher ID, and claim ID, not the full URL or claimant data.

## Execution and recovery

The submitting claim session receives an opaque capability saved before execution. Only its hash is stored with the claim. The backend success endpoint chooses a terminal navigation to the handoff gate once a matching claim has a successful terminal status and no reconciliation requirement. The gate records requested/prepared evidence and responds with Inertia location or an ordinary 303 redirect. No intermediate success countdown is needed. Pending completion is polled for at most two minutes while the page is visible, with a manual status retry after interruption.

Reopen the success URL in the same authorized session to recover a lost response. Navigation retries never invoke execution. A different browser session or an API/CLI claim does not obtain browser handoff authorization merely by knowing a Pay Code. For reusable vouchers, the latest claim bound to the requesting session must qualify. This is same-session recovery, not a new cross-device recovery mechanism.

The local outbox is committed with successful claim recording. `php artisan x-change:rider:deliver-journal --limit=100 --no-interaction` replays it into x-journal using stable event identifiers. Failed deliveries retain their rows, attempt count and exception class; they retry on the next scheduler run. Monitor the pending row count and oldest occurred_at in `x_change_rider_redirect_events`, and scheduler/command failures. Investigate any growing backlog; reporting failure does not reverse payment. The Cockpit Pay Code detail shows delivery evidence after existing access checks.

## Optional destination acknowledgments

Configure `rider-redirect.receipt_keys` as an exact lowercase host-to-secret map in host configuration, sourcing secrets from the environment or secret manager. Never commit actual keys. When a key exists at issuance, the frozen policy opts into receipts and navigation appends `x_rider_handoff=<opaque attempt UUID>` before any fragment. The parameter is reserved; issuance rejects destinations already containing it.

A cooperating destination server POSTs JSON to named route `x-change.claim.rider.receipts` (`/x/claim/rider/receipts` with the default prefix):

```json
{"reference":"<handoff UUID>","event":"destination_received"}
```

Allowed events are `destination_received` and `page_initialized`. The latter must be sent by the destination server after its own browser initialization signal, never by exposing the shared key in browser JavaScript. It is explicitly destination-reported evidence, not proof of human attention.

Send `X-Rider-Timestamp` as Unix seconds and `X-Rider-Signature` as lowercase hexadecimal HMAC-SHA256 of `timestamp + "." + exact raw JSON body`, keyed by the destination's shared secret. The default clock window is 300 seconds. The reference must match a prepared attempt with receipts enabled. Duplicate acknowledgments are idempotent; fresh authenticated retries are supported. Keep the configured key available while in-flight acknowledgments are expected. No cookies or claimant CSRF token are required; this endpoint uses HMAC authentication and throttling.

## Evidence meanings

Committed means a destination was frozen. Eligible means a successful claim was recorded. Handoff requested means an authorized browser reached the gate. Redirect prepared means our server committed its intent before returning navigation. Destination received and page initialized require authenticated destination reports. None of these proves that the claimant read the page. Ordinary URLs remain arrival-unconfirmed.

No iframe, destination mirroring, hosted landing pages, floating palette on arbitrary sites, fee adjustment, or CDN acceleration is introduced.
