# Medicard Gate M7 — Private Synthetic Rehearsal

Date: 2026-10-09

## Disposition

Gate M7 is complete.

Testing contains one paused private Medicard rehearsal campaign:

- campaign reference: `01M4F5JP42152MWCJHFZSE8W89`;
- immutable template revision: `pctv_49fa84aba1994ef8a2d245c0c7b822c2e2ea51b9`;
- driver: `medicard.demo-benefit@1.0.0`;
- campaign status: `paused`;
- payment QR bindings: `0`; and
- monitoring controls: `0`.

Both Standing Funding schedule switches remained false throughout the gate.

## Synthetic Lifecycle Acceptance

The focused package acceptance passed 1 test / 22 assertions. It exercised the
complete Medicard demonstration contract against synthetic provider and SMS
boundaries:

- one recognized PHP 50 payment fixture;
- one provisional demo-benefit record;
- one settlement envelope;
- one zero-value completion Pay Code;
- one claim evidence projection;
- one completion request;
- one deterministic `benefit_ready_demo` outcome;
- two queued SMS delivery intents; and
- mutation-free completion replay.

No external HTTP request, NetBank request, live SMS, Medicard API call, or money
movement occurred. The focused Demo Benefit Summary frontend test also passed.

## Authenticated Cockpit Acceptance

The authenticated testing Cockpit showed the campaign once with these operator
facts:

- `Paused`;
- `0 payments received`;
- `0 details submitted · 0 demo benefits ready`;
- `0 awaiting claim`; and
- `Payment QR Ph not created`.

The campaign activity view correctly showed no lifecycle records. No browser
console errors were observed. The activity page still uses the generic legacy
heading “Policy lifecycle”; that is presentation copy debt, not a financial or
runtime safety defect, and should be polished before broader stakeholder use.

## Recovery Note

The guarded creation transaction succeeded, but its first diagnostic command
ended non-zero after commit because the diagnostic referenced a relationship
method that does not exist. The stable rehearsal key was not replayed as a
creation command. A direct read verified exactly one campaign, its immutable
revision, zero bindings, zero controls, and empty queues.

## Final Safety Snapshot

After browser acceptance, Cloud remained at:

| Fact | Count |
| --- | ---: |
| Campaign payment recognitions | 13 |
| Provisional coverages | 13 |
| Completion Pay Code issuances | 13 |
| Completion requests | 11 |
| Completion outcomes | 11 |
| Feedback delivery records | 42 |
| Queued database jobs | 0 |
| Failed jobs | 0 |

Gate M8 remains separate. It requires explicit financial authorization before
provisioning a payment-purpose address, enabling campaign-only monitoring, or
accepting a live payment.
