# Commercial Governance Architecture

The active price resolver reads `x_change_commercial_offering_activations`, follows its immutable offering reference, and validates snapshot-hash parity before returning the x-commerce DTO. There is no configuration switch that can silently substitute a published row for the active version.

The primary records are:

- `x_change_commercial_offerings`: immutable catalog, waterfall, attribution, legal trace, origin, provenance, lifecycle, and human approval references;
- `x_change_commercial_offering_activations`: append-only activation evidence with one current activation per profile;
- `x_change_commercial_operator_authorizations`: time-bounded named capabilities;
- x-journal execution entries: baseline provisioning, draft, submission, publication, activation, and operator authorization evidence;
- Commercial Sales: the exact offering and hash accepted by an issuance operation.

`bootstrap_immutable` is not an authorization bypass. It is a distinct commissioning authority whose permitted operation is limited to activating the exact package-defined baseline. It cannot author arbitrary prices or impersonate a person.

The public resolution boundary remains provider-neutral and institution-neutral. Profiles describe commercial use (`pay_code`, `account_funding`); provider costs and Treasury recipients live inside the governed waterfall rather than branching the resolver for a bank or EMI name.
