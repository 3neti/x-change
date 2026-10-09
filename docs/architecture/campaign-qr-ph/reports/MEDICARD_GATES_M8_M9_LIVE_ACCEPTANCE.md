# Medicard Gates M8 and M9 — Live Acceptance and Closeout

Date: 2026-10-09

## Disposition

Gates M8 and M9 are complete. The presentation window is closed and the testing
runtime has returned to its safe posture.

This remained a demonstration-only journey. It did not call a Medicard API or
create membership, healthcare coverage, treatment authorization, reimbursement
rights, or provider entitlement.

## Commissioned Boundary

- campaign: `01M4F5JP42152MWCJHFZSE8W89`;
- immutable revision: `pctv_49fa84aba1994ef8a2d245c0c7b822c2e2ea51b9`;
- driver: `medicard.demo-benefit@1.0.0`;
- payment QR binding: `01M4F64M1HPFCQN1XZDQ7VHWRR`;
- fixed amount: PHP 50;
- global Standing Funding schedule: disabled throughout; and
- campaign polling: batch one, Medicard binding only during the window.

## M8 Dress Rehearsal Evidence

NetBank observation `2848489` produced recognition 14,
`01M4F6ZZVAPQBKGMGBFVNV3TTS`. It produced exactly one:

- provisional Medicard demonstration coverage;
- completion Pay Code `POLI-TWLQ`;
- beneficiary claim projection;
- automatic demonstration completion request;
- deterministic `benefit_ready_demo` outcome; and
- initial claim SMS plus private summary SMS, both provider-accepted.

Immediate replay preserved one coverage, one issuance, one request, one
outcome, and two feedback records.

## Fail-Closed Recovery

The recognized payment exposed two deployment gaps. The lifecycle handoff was
restricted to the AUI driver, and the host registry did not contain the
Medicard driver manifest. Both failures occurred before partial coverage,
issuance, or SMS state was created.

The recovery published and adopted:

- x-change `v1.0.115`, routing campaign lifecycle by exact registered driver;
- x-change `v1.0.116`, accepting the strict persisted Medicard product shape
  while rejecting wrong name, premium, duration, currency, or driver version;
  and
- host commit `45891ba5`, materializing the exact Medicard YAML driver.

Focused Medicard verification passed 7 tests / 45 assertions before the live
replay. The repaired replay completed exactly once.

## M9 Presentation Evidence

A second distinct payment occurred during the authorized presentation window.
NetBank observation `2848623` produced recognition 15,
`01M4FDNRNGW926ENQHDXTHPM9H`. It independently completed through:

- provisional coverage `01M4FDNTHH3C18F7X98MBNXMHP`;
- completion Pay Code `POLI-JPU4`;
- claim projection `01M4FE9P9WA4G8ZF3Y23MS5VP1`;
- completion request `01M4FE9PYXESF40DVNF5ZYS64F`;
- outcome `01M4FE9Q330YBBR51R9NHD8HBD`; and
- two provider-accepted SMS records.

This was a distinct provider payment, not a replay duplicate.

## Final Safe Posture

After the user declared the presentation finished:

- Medicard monitoring was paused at generation 2;
- AUI monitoring remained paused at generation 2;
- `XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED=false`;
- `XCHANGE_CAMPAIGN_PAYMENT_SCHEDULED_SYNC_ENABLED=false`;
- the automatic demonstration campaign whitelist was cleared;
- queued database jobs: 0; and
- failed jobs: 0.

Deployment `depl-a2f0137c-69c2-4d90-93b1-34d9e66f5771` materialized and verified
that closeout posture. Historical lifecycle evidence remains append-only and
readable.
