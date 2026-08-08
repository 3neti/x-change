# Bank Operating Model

An institution does not need three interactive accounts on first deployment. It needs one non-interactive System Principal so Treasury and package operations have a stable owner. People are onboarded normally when the institution is ready to change commercial terms.

| Principal | Initial deployment | May issue | May change prices |
| --- | --- | --- | --- |
| System Principal | Required, non-interactive | Owns system operations | Never maker or checker |
| Account holder | Onboarded as needed | According to Client Funds and policy | No |
| Commercial maker | Optional initially | Normal Account rights | Drafts and submits revisions |
| Commercial checker | Optional initially | Normal Account rights | Independently approves and activates |
| Partner maker | Optional until commissions apply | Normal Account rights | Registers Partners and destination revisions |
| Partner checker | Optional until commissions apply | Normal Account rights | Independently approves Partner records and destinations |
| Settlement maker | Optional until commission payout | Normal Account rights | Requests an earned-commission payout batch |
| Settlement checker | Optional until commission payout | Normal Account rights | Independently approves the batch |

The default compromise is operationally useful and governable:

- baseline prices are package facts, not a person's discretionary decision;
- unchanged prices permit controlled issuance;
- no one can edit the baseline record;
- changes are impossible until distinct maker and checker authorities exist;
- approval does not silently activate a version;
- journal evidence identifies the actor, authority reference, snapshot hash, and lifecycle event.

Institutions should bind authorization references to their own board resolution, delegated-authority register, change ticket, or comparable control evidence. The reference is an audit linkage, not a secret.

For casual depositors and ordinary Account holders, this machinery stays out of view. They see the price estimate attached to issuance, not operator identities, approval records, revenue positions, or provider-cost policy.

The Cockpit Commercial workspace is the operational manifestation:

- **Price List** and **Waterfall** govern the terms applied to new sales;
- **Partners** registers legal payees, attribution terms, masked balances, and encrypted settlement destinations;
- **Operations** records authoritative provider-cost evidence and controls commission payout batches;
- **Activity** shows accepted sales and their immutable allocation snapshots;
- **Policy** exposes the applicable x-legal trace without recasting commissions as Client Funds.

No Commercial Operations page calls a provider on load. Provider submission and reconciliation are explicit, capability-protected actions. Actual provider costs and Partner commission payments are operational transactions that settle existing system Positions; they are not discretionary edits to a user's Account.
