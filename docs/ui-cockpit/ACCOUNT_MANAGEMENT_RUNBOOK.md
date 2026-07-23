# Cockpit Account Management Runbook

## Operator Goal

Use `/x/cockpit/accounts` to select the provider destination that will appear on future Funding Intents. Saving a destination does not add money and does not change Internal Balance or Usable Balance.

## Before Changing a Destination

- Confirm the Account reference shown in the header.
- Confirm whether the Account should use the platform-managed shared destination or its own dedicated destination.
- Obtain the destination details through an approved channel.
- Never paste a token into chat, a ticket, logs, or screenshots.
- Expect a recent security PIN confirmation before a mutation is accepted.

## NetBank

### Use shared treasury

Select **Shared treasury** and save. Future Funding Intents use the platform-configured corporate account and VCA routing. Existing intents keep their original snapshot.

### Enroll a dedicated account

1. Select **Dedicated account**.
2. Choose **Generate with NetBank** when NetBank should issue the alias token.
3. Enter the corporate account number, exact account name, and five-digit VCA alias.
4. Save and complete recent-PIN confirmation if requested.
5. Confirm the returned state is ready and the displayed reference is masked.

Use **Import existing token** only when an approved token already exists. The token is write-only and is cleared from the form after success.

### Rotate a token

Open **Rotate dedicated VCA token**, read the warning, confirm the operation, and submit. Rotation is separate because it changes the active funding credential. If rotation fails, treat the dedicated rail as unavailable until its state is confirmed; do not switch silently.

## Paynamics

### Use shared wallet

Select **Shared wallet** and save. Future Funding Intents use the platform-configured wallet.

### Record a dedicated wallet candidate

1. Select **Dedicated wallet**.
2. Enter the wallet ID.
3. Save and complete recent-PIN confirmation if requested.
4. Review verification state.

A successful reachability or balance lookup does not prove ownership. If the state is not `ownership_verified`, dedicated Funding remains blocked. Use shared mode explicitly if policy permits; x-change will not fall back automatically.

## Funding Page Checks

Before creating a Funding Intent:

- confirm the provider card shows the intended shared/dedicated mode;
- confirm its masked destination reference;
- confirm status is **available**, not **blocked**;
- enter the exact transfer amount;
- retain the issued intent reference with the external transfer record.

An intent submission produces instructions only. Balance changes after authoritative provider settlement verification.

## Connection History

Connection history is retained for investigation and audit. A prior connection does not become active merely because it appears in history. Use the current mode and provider status at the top of each card as the operational state.

## Lifecycle Walkthrough

Use **Run safe walkthrough** on the Accounts page to inspect the complete destination lifecycle without changing the Account.

The walkthrough runs all seven scenario states in one rollback-only request and then presents them through Previous, Next, and Restart controls. It demonstrates:

- shared provider defaults;
- dedicated NetBank eligibility;
- immutable Funding Intent destination snapshots;
- separate NetBank token rotation;
- Paynamics reachability blocked without ownership proof;
- ownership-verified Paynamics eligibility;
- return to shared mode with connection history.

The walkthrough must always show:

- rollback confirmed;
- zero provider calls;
- unchanged balance;
- no retained records;
- no Funding instructions or webhook processing.

If the walkthrough reports that rollback could not be confirmed, stop using it and investigate the application database transaction state. Do not interpret any walkthrough state as provider evidence.

Engineering and CI may run the same scenario with:

```bash
php artisan xchange:lifecycle:run \
    account_management_funding_destinations_demo \
    --issuer=<operator-id> \
    --json
```

The Cockpit control is enabled by default outside production. Production requires `XCHANGE_COCKPIT_ACCOUNT_SCENARIO_ENABLED=true`.

## Incident Handling

| Condition | Operator response |
|---|---|
| Dedicated provider is blocked | Stop creating intents on that rail; verify credential or ownership state |
| Webhook arrived but balance did not change | Check Funding Intent and verification state; webhook receipt alone is not a credit |
| Amount or destination mismatch | Leave the case in suspense and use maker-checker reconciliation |
| Duplicate provider notification | Do not create a manual credit; idempotent processing should absorb it |
| NetBank token suspected exposed | Rotate through the warned operation and follow credential incident policy |
| Paynamics wallet is reachable but unverified | Keep dedicated funding blocked |
| Provider reversal | Review recovery/impairment state; do not edit historical settlement |

## Browser Acceptance

Check desktop and narrow mobile widths:

- Accounts appears in secondary Cockpit navigation.
- Header and provider cards do not overflow.
- NetBank and Paynamics cards stack cleanly on narrow screens.
- Dedicated fields appear only when dedicated mode is selected.
- Imported NetBank token uses a password input and is never prefilled.
- Paynamics warning remains visible in dedicated mode.
- Lifecycle walkthrough advances through all seven masked steps.
- Walkthrough controls stack without overflow on narrow screens.
- Walkthrough completion shows rollback, unchanged balance, and no persistence.
- Blocked provider options are disabled on Funding.
- Profile provider cards contain no mutation forms and link to Accounts.
- No unmasked account number, wallet ID, or token appears in page text or browser history.
