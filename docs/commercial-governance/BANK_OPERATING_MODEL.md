# Bank Operating Model

An institution does not need three interactive accounts on first deployment. It needs one non-interactive System Principal so Treasury and package operations have a stable owner. People are onboarded normally when the institution is ready to change commercial terms.

| Principal | Initial deployment | May issue | May change prices |
| --- | --- | --- | --- |
| System Principal | Required, non-interactive | Owns system operations | Never maker or checker |
| Account holder | Onboarded as needed | According to Client Funds and policy | No |
| Commercial maker | Optional initially | Normal Account rights | Drafts and submits revisions |
| Commercial checker | Optional initially | Normal Account rights | Independently approves and activates |

The default compromise is operationally useful and governable:

- baseline prices are package facts, not a person's discretionary decision;
- unchanged prices permit controlled issuance;
- no one can edit the baseline record;
- changes are impossible until distinct maker and checker authorities exist;
- approval does not silently activate a version;
- journal evidence identifies the actor, authority reference, snapshot hash, and lifecycle event.

Institutions should bind authorization references to their own board resolution, delegated-authority register, change ticket, or comparable control evidence. The reference is an audit linkage, not a secret.

For casual depositors and ordinary Account holders, this machinery stays out of view. They see the price estimate attached to issuance, not operator identities, approval records, revenue positions, or provider-cost policy.
