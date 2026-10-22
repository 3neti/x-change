# Rider URL handoff after claim acceptance

A paid `rider.url` is a frozen voucher obligation. The destination is validated before issuance and resolved again at the redirect gate. For disbursement claims, the final claim submission prepares a handoff only after an accepted, completed claim is persisted for the same voucher and idempotency key. Provider transfer settlement is separate: `Pending`, an immediate payout failure, or a later payout failure does not undo an accepted claim's rider handoff. An unaccepted claim never receives one.

The server stores a ten-minute handoff receipt in the claimant browser session and journals `rider.handoff.prepared`. An Inertia submission returns `Inertia::location()` to the first-party `/x/claim/{code}/redirect` gate, causing a full same-tab visit. The gate verifies the receipt, resolves and revalidates the frozen HTTPS destination through x-rider, journals `rider.redirect.started`, and redirects to the external URL. If preparation, validation, or journaling fails, the claimant remains on the success page with a handoff failure notice. The frontend does not run a timer or navigate directly to the external URL.

The prepared and started events prove server-side handoff decisions, not that the destination loaded or was viewed. Keep payout reconciliation and destination-arrival evidence separate. A retry of the redirect gate must not submit another claim or repeat the provider transfer.

Verify with the focused claim submit and redirect feature tests, the frontend success-redirect test, and an actual browser claim that returns a pending provider transfer. Check the same-tab destination, claim record, provider result, journal events, browser console, and server logs.
