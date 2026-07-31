# Instruction Package Acquisition and Deprecation Plan

## Status

Proposed migration plan. No package removal is authorized by this document until
every mandatory release gate is passing.

This plan treats the retirement of `3neti/instruction` as a compatibility-first
acquisition:

- `3neti/x-commerce` survives as the commercial authority;
- `3neti/x-change` acquires the runtime and historical compatibility assets;
- `3neti/instruction` remains operational during the bridge, then becomes a
  deprecated compatibility package; and
- applications, users, financial records, reports, routes, and tests must
  behave as though package ownership did not change.

The target is package rationalization without a product, pricing, data, or
ledger migration being visible to consumers.

## Executive Decision

`3neti/instruction` must not be removed from Composer today.

Immediate removal is a critical-risk change because x-change still depends on
Instruction for:

- the wallet-enabled `InstructionItem` model;
- the persistent model class stored by Bavix Wallet;
- the `instruction_items` and `instruction_item_price_histories` migrations;
- service-provider bindings and legacy configuration;
- the `api/instruction/v1/estimate` compatibility endpoint;
- lifecycle seeding;
- revenue allocation, collection, snapshots, and reports; and
- the x-change package test harness.

The acquisition is approved only as a phased transfer with permanent
compatibility for persistent identities.

## Deal Thesis

### Acquirer

`3neti/x-commerce` is the surviving commercial authority. It owns the meaning
and deterministic calculation of commercial terms.

### Operating company

`3neti/x-change` remains the execution and Treasury runtime. It owns database
models, migrations, wallets, transfers, collections, reports, commands, HTTP
compatibility, and provider-facing execution.

### Seller

`3neti/instruction` is the package being deprecated. Its package boundary may
disappear, but persistent identities created by the package do not disappear.

### Consideration paid

The cost of the acquisition is a permanent compatibility obligation:

- preserve the legacy `InstructionItem` fully qualified class name;
- preserve existing table names, primary keys, and migration names;
- preserve historical wallet and transaction relationships;
- preserve the legacy HTTP contract during its announced deprecation window;
- preserve current x-change pricing behavior; and
- retain an installable deprecated Instruction release for downstream users.

## Non-Negotiable Invariants

The following invariants apply throughout the migration.

1. No wallet balance, transaction, transfer, revenue collection, price history,
   or report row may become unreachable.
2. The persistent model identity
   `LBHurtado\Instruction\Models\InstructionItem` remains valid indefinitely.
3. The tables `instruction_items`, `instruction_item_price_histories`, and
   `revenue_collections` are not renamed or dropped during this acquisition.
4. Existing instruction-item IDs are not remapped.
5. The original Instruction migration filenames are preserved exactly.
6. No release combines package removal with a destructive schema or data
   migration.
7. Current x-change pricing output is byte-for-byte equivalent before and after
   cutover for the same normalized input and configuration.
8. The legacy Instruction API remains contract-compatible during its
   deprecation window.
9. x-commerce remains free of Eloquent, Bavix Wallet, x-change, provider, and
   execution dependencies.
10. Composer dependency direction remains acyclic.
11. A failed cutover can return to the bridge release without reversing a
    destructive migration.
12. All mandatory tests and reconciliation checks pass before each release is
    promoted.

## Current Evidence Baseline

The following values describe the inspected development database. They are
evidence that persistent compatibility matters; they are not substitutes for a
fresh production preflight at deployment time.

| Measure | Observed value |
| --- | ---: |
| Instruction items | 28 |
| Instruction-item wallets | 22 |
| Instruction-item wallets with non-zero balances | 22 |
| Combined instruction-item wallet balance | 1,034,820 minor units |
| Transactions with the legacy InstructionItem payable type | 779 |
| Instruction item price-history rows | 0 |
| Revenue collection rows | 0 |
| x-change commercial sales | 23 |
| x-change commercial allocations | 92 |

All 22 observed wallets use
`LBHurtado\Instruction\Models\InstructionItem` as `wallets.holder_type`.
The same class identity appears in the related transaction records.

Changing only the PHP namespace could therefore make historical wallets appear
empty and allow Bavix Wallet to create parallel wallets for the same
instruction-item IDs.

## Target Ownership

| Asset or responsibility | Final owner | Compatibility treatment |
| --- | --- | --- |
| Versioned commercial catalogs | x-commerce | Canonical source of new prices |
| Catalog item references and metadata | x-commerce | Versioned and immutable once quoted |
| Deterministic commercial quotes | x-commerce | Existing quote behavior remains deterministic |
| Commercial sale snapshots | x-commerce | Persisted and posted by x-change |
| Commercial Waterfall calculation | x-commerce | Pure calculation only |
| Instruction-to-catalog selection adapter | x-change | Converts execution input into commercial input |
| `InstructionItem` Eloquent model | x-change | Keeps the legacy Instruction FQCN |
| `InstructionItemPriceHistory` model | x-change | Keeps table, relation, and historical records |
| Instruction-item wallets | x-change | Keeps legacy morph identity |
| Instruction migrations | x-change | Keeps exact original filenames and definitions |
| Revenue allocation and collection | x-change | Uses real wallets and Treasury controls |
| Revenue snapshots and reports | x-change | Continues to read historical records |
| Lifecycle preparation and seeding | x-change | Derives runtime rows from approved commercial data |
| Legacy Instruction repository and evaluator | x-change compatibility layer | Preserved only for the deprecation contract |
| Legacy Instruction API route | x-change compatibility layer | Same path, route name, middleware, validation, and body |
| Deprecated Composer package | instruction shim | Requires the cutover x-change release |

`x-commerce` must not declare that it replaces `3neti/instruction`. It does not
provide Instruction's model, wallet, migrations, route, reports, or runtime
bindings.

## Persistent Identity Strategy

Package ownership and PHP namespace ownership are separate concerns.

At cutover, x-change may supply classes under the
`LBHurtado\Instruction\...` namespace even though the Instruction package is no
longer installed. The wallet-bearing model must continue to be:

```text
LBHurtado\Instruction\Models\InstructionItem
```

This is the preferred strategy because it preserves Bavix Wallet lookups
without rewriting ledger identity or depending on service-provider ordering.

A Laravel morph map may be added as defense in depth, but it must not be the
only compatibility mechanism. A global morph map introduces ordering and
integration risks, while the existing FQCN is already durable data.

Introducing a new stable morph alias and rewriting historical morph values is
outside this acquisition. It would be a separate, explicitly authorized ledger
migration with its own reconciliation and rollback plan.

## Pricing Compatibility Strategy

There are two different pricing contracts and they must not be conflated.

### Current x-change contract

Current x-change pricing already delegates to the x-commerce catalog through
the commercial quote adapter. The shared 28 catalog prices in the inspected
configuration match; x-commerce additionally defines
`flow_type.collectible`.

This contract must remain unchanged by the package acquisition.

### Legacy Instruction contract

The standalone Instruction package represents an older pricing contract. It
includes behavior such as:

- a different historical seeded tariff;
- fixed and open slice fees;
- customer-aware system-user exemptions;
- truthy-value evaluation rules;
- legacy request validation; and
- the legacy charge-estimate response shape.

These differences are not automatically defects. They are compatibility
obligations for users of the old package and endpoint.

The migration therefore uses two golden-master suites:

1. current x-change pricing before versus after acquisition; and
2. legacy Instruction endpoint behavior before versus after transfer to the
   compatibility layer.

The legacy contract may later be retired through a separately announced
breaking release. It must not be silently rewritten to match current
x-commerce prices.

## Revenue Transition Strategy

x-change currently contains both:

- the newer commercial-sale and Treasury allocation path; and
- legacy instruction-item wallet allocation and collection services.

Package removal is not a financial cutover mechanism.

Before retiring new legacy accrual, the implementation must:

1. identify which issuance mode creates instruction-item wallet revenue;
2. define and record a financial cutover timestamp;
3. stop new legacy accrual only after the commercial path is proven;
4. reconcile all instruction-item wallet balances and transactions;
5. collect, carry forward, or formally classify every outstanding balance;
6. retain combined historical reporting while either system has relevant
   records; and
7. prove that commercial and legacy totals are not double-counted.

Legacy wallets and reports remain readable even after their balances reach
zero.

## Composer Dependency Rule

The final dependency direction is:

```text
3neti/instruction deprecated shim
    -> 3neti/x-change
        -> 3neti/x-commerce
```

The following arrangements are prohibited:

- x-commerce requiring x-change;
- x-commerce requiring Instruction;
- x-change requiring an Instruction shim that already requires x-change;
- declaring x-commerce as a Composer replacement for Instruction before it
  provides the complete public surface; or
- publishing the shim before the cutover x-change release is available.

The current x-change requirement is `3neti/instruction:^0.2`. The deprecated
shim should use a new minor line, such as `0.3`, after x-change no longer
requires Instruction. This avoids creating a Composer cycle for older x-change
releases.

## Migration Ownership Rule

The acquiring package must preserve these migration identities:

```text
2024_08_02_317537_create_instruction_items_table
2024_08_03_317537_create_instruction_item_price_histories_table
```

The original migration files have already run in deployed databases and must
not be edited to express later schema changes.

During the bridge:

- x-change acquires byte-equivalent copies under the original filenames;
- the test suite proves Laravel does not execute either migration twice while
  both packages are installed; and
- fresh installations prove both tables are created exactly once.

After cutover:

- x-change loads the acquired migrations without Instruction installed;
- existing databases recognize the original migration names as complete; and
- fresh databases receive the same schema.

Any later schema change uses a new forward migration owned by x-change.

## Release Plan

### Phase 0: Freeze and characterize

Risk level: medium while the existing package remains installed.

Deliverables:

- tag or record the exact Instruction, x-change, and x-commerce baselines;
- capture the public Instruction API request, validation, and response
  contract;
- capture current x-change pricing fixtures;
- capture legacy evaluator fixtures, including slice fees and system-user
  behavior;
- capture schema definitions and migration repository names;
- capture preflight financial counts and totals;
- add architecture inventory for every direct Instruction dependency; and
- establish a downstream-consumer register.

Exit gate:

- all existing package and host suites are green;
- the golden masters are committed; and
- the financial baseline can be regenerated by an automated, read-only check.

### Phase 1: Stabilize x-commerce as commercial authority

Risk level: low.

Deliverables:

- tag an immutable x-commerce release;
- retain the canonical versioned Pay Code catalog;
- retain deterministic quote and sale-snapshot behavior;
- document every compatibility mapping from a catalog item to a runtime
  revenue destination;
- classify legacy-only concepts such as slice fees and system-user exemptions;
  and
- prohibit runtime, database, wallet, and provider dependencies in
  x-commerce.

Exit gate:

- x-commerce boundary tests pass;
- catalog and quote tests pass independently;
- the x-change adapter passes the current-pricing golden master; and
- every positive current catalog charge has an explicit runtime allocation
  destination.

### Phase 2: Build the x-change bridge

Risk level: medium.

x-change still requires Instruction in this phase.

Deliverables:

- acquire exact copies of the two Instruction migrations;
- introduce x-change-owned runtime factories and seeders without changing
  existing records;
- consolidate the configured instruction-item model default;
- preserve the legacy `instruction.*` configuration values;
- add concrete tests for allocation, collection, snapshots, and reports;
- make lifecycle preparation idempotent;
- prevent lifecycle preparation from clearing an existing
  `revenue_destination_type` or `revenue_destination_id`;
- add upgrade fixtures containing old-class wallets, transactions, transfers,
  histories, and collections; and
- add a clean-install test application.

The bridge must not define duplicate legacy classes or duplicate route
ownership while the original provider remains active.

Exit gate:

- fresh install with both packages succeeds;
- repeated migration and lifecycle preparation are idempotent;
- old wallet identities resolve to the same wallet IDs and balances;
- all real revenue services and reports pass;
- package discovery and configuration caching pass; and
- no production behavior has switched yet.

### Phase 3: Cut over runtime ownership

Risk level: high until every cutover gate passes.

Deliverables:

- remove `3neti/instruction` from x-change's Composer requirements;
- have x-change provide the required legacy Instruction namespace;
- keep `LBHurtado\Instruction\Models\InstructionItem` as the model identity;
- transfer repository bindings, compatibility configuration, migrations,
  factories, seeders, and the legacy endpoint to x-change;
- transfer the old route atomically so two providers never own it together;
- update the x-change test harness so it no longer registers or reflects on
  the external Instruction provider;
- add an architecture test forbidding dependencies on the external package;
  and
- run a clean Composer installation with only the surviving runtime packages.

Deployment operation:

1. enter the normal maintenance or controlled-deployment window;
2. capture the production financial preflight;
3. install the cutover dependency graph;
4. rebuild Composer package discovery;
5. clear and rebuild application configuration and route caches;
6. run migrations;
7. execute the post-cutover reconciliation;
8. run the production smoke suite; and
9. reload queue workers and other long-running PHP processes.

Exit gate:

- every preflight and postflight financial measure is equal;
- no new wallet was created for an existing instruction item;
- legacy and current pricing golden masters pass;
- the legacy endpoint contract passes;
- all package and host suites pass; and
- rollback to the bridge artifact remains possible.

### Phase 4: Publish the deprecated Instruction shim

Risk level: low after Phase 3 is stable.

Deliverables:

- publish a new Instruction minor release marked deprecated;
- make the shim require the compatible cutover x-change release;
- remove duplicate implementation from the shim;
- retain installation and migration guidance for downstream consumers;
- emit deprecation notices that do not change runtime response bodies; and
- announce the support and removal timetable.

The shim is published only after x-change no longer requires Instruction.

Exit gate:

- a clean application requiring the shim boots successfully;
- the old namespace, provider behavior, route, and API contract work;
- Composer resolves without a cycle; and
- users have a documented direct migration path to x-change and x-commerce.

### Phase 5: Close the acquisition

Risk level: low and controlled.

Deliverables:

- mark the Instruction repository read-only or maintenance-only;
- retain the final supported release on the package registry;
- point all new commercial integrations to x-commerce;
- point all runtime integrations to x-change;
- remove temporary dual-run instrumentation;
- retain permanent persistent-identity compatibility;
- archive the downstream-consumer register and cutover evidence; and
- record the final decision and package-support dates.

The old model FQCN, tables, migration names, and historical read paths are not
removed by acquisition closure.

## Mandatory Test Gates

### Architecture and dependency gates

- A clean Composer install succeeds with x-change and x-commerce but without
  Instruction.
- Package discovery boots the application.
- Configuration and route caching complete successfully.
- No external `3neti/instruction` dependency remains in x-change.
- No `LBHurtado\Instruction` reference exists outside the intentional
  compatibility namespace and tests.
- x-commerce has no Eloquent, wallet, x-change, or provider dependency.

### Pricing gates

- Current x-change estimates match their pre-acquisition golden masters.
- Catalog reference, version, selected lines, quantities, totals, and quote
  references remain deterministic.
- Legacy evaluator fixtures retain truthy-value, validation, count, slice-fee,
  and system-user semantics.
- Deprecated catalog items continue to fail closed for new quotes.
- Current and legacy pricing fixtures are clearly separated.

### Migration and installation gates

- Fresh installation with the bridge packages creates each table once.
- Fresh installation with only surviving packages creates the same schema.
- Upgrade from the exact legacy migration repository runs no duplicate create.
- Existing item IDs and price-history foreign keys remain unchanged.
- Rollback to the bridge release requires no reverse data migration.

### Wallet and revenue gates

- A fixture with the old `wallets.holder_type` resolves through the acquired
  model.
- Pay Code issuance passes with the commercial path enabled and with the legacy
  instruction-item allocation path enabled.
- Wallet ID, UUID, holder type, balance, and transaction count remain equal
  before and after upgrade.
- Real issuer debit and instruction-item credit use Bavix Wallet, not mocks.
- Retry and idempotency behavior cannot double-charge.
- Collection transfers the correct balance to the configured destination.
- A successful collection creates the expected `revenue_collections` row.
- Pending snapshots report the same balances before and after cutover.
- Legacy and commercial revenue are not double-counted.

### Lifecycle gates

- Lifecycle preparation succeeds from an empty database.
- Running preparation twice does not create duplicates or change timestamps
  unnecessarily.
- Every expected index, type, price, currency, label, category, and deprecation
  flag is correct.
- Existing revenue destinations survive repeated preparation.
- Every compatibility charge index resolves to a wallet-bearing runtime item
  when legacy allocation is enabled.

### Report gates

- Revenue summary totals remain stable.
- Revenue-by-instruction labels, filters, sorting, and pagination remain
  stable.
- Collection reports retain destination and transfer references.
- Historical instruction names and IDs remain resolvable.
- Timezone conversion remains unchanged.

### HTTP gates

- The old path and route name remain available during the deprecation window.
- Middleware and authorization remain equivalent.
- Validation errors retain status and JSON shape.
- Successful estimates retain field names, numeric units, and ordering where
  ordering is contractual.
- Deprecation headers or logs do not alter the response body.
- The current x-change estimate endpoint remains unchanged.

### Deployment gates

- Production preflight is read-only.
- Preflight and postflight compare item counts, wallet counts, holder types,
  wallet balances, transaction counts, transfer counts, collection totals,
  commercial sales, and commercial allocations.
- No pre-existing instruction item receives a second wallet.
- Application, queue, scheduler, and command workers load the cutover classes.
- The bridge release and lock file remain available for rollback.

## Risk Register

| ID | Risk | Severity before mitigation | Mitigation | Release blocker |
| --- | --- | --- | --- | --- |
| IAR-001 | Legacy wallet identity becomes unreachable | Critical | Preserve the exact InstructionItem FQCN and verify wallet identity | Yes |
| IAR-002 | New model creates parallel wallets | Critical | Assert one wallet per existing item and unchanged wallet IDs | Yes |
| IAR-003 | Acquired migrations recreate existing tables | Critical | Preserve exact migration names and test legacy upgrades | Yes |
| IAR-004 | Fresh installs omit Instruction tables | Critical | x-change owns and loads exact acquired migrations | Yes |
| IAR-005 | Composer dependency cycle | Critical | Enforce `shim -> x-change -> x-commerce` release ordering | Yes |
| IAR-006 | Provider removal loses route or bindings | High | Transfer provider responsibilities atomically | Yes |
| IAR-007 | Pricing changes silently | High | Maintain separate current and legacy golden masters | Yes |
| IAR-008 | Slice fees or system exemptions disappear | High | Preserve in compatibility contract or retire explicitly later | Yes |
| IAR-009 | Historical revenue becomes invisible | Critical | Keep legacy tables, models, wallets, and combined reports | Yes |
| IAR-010 | Revenue is double-counted across systems | Critical | Financial cutover timestamp and reconciliation | Yes |
| IAR-011 | Lifecycle preparation clears destinations | High | Preserve existing destination columns and add idempotence tests | Yes |
| IAR-012 | Published or cached config names the removed package | High | Normalize defaults and test config cache during deployment | Yes |
| IAR-013 | Duplicate legacy route registration | High | Transfer route only in the provider-removal release | Yes |
| IAR-014 | External standalone consumers break | High | Consumer register, deprecated shim, and support window | Yes |
| IAR-015 | Root and package lock files test different graphs | High | Tag immutable releases and test both dependency graphs | Yes |
| IAR-016 | Green unit tests hide real transfer failures | High | Require concrete wallet, collection, report, and upgrade tests | Yes |
| IAR-017 | Rollback requires destructive data reversal | Critical | No destructive migration in the cutover release | Yes |
| IAR-018 | x-commerce absorbs runtime concerns | High | Retain package boundary architecture tests | Yes |

## Rollback Plan

Rollback is a release operation, not a database restore, because the cutover
must make no destructive schema or data change.

If any cutover gate fails:

1. stop new issuance and collection activity;
2. capture the failed postflight for investigation;
3. restore the bridge application artifact and its exact lock file;
4. rebuild Composer discovery and application caches;
5. reload long-running workers;
6. rerun the financial reconciliation;
7. confirm that wallet IDs, balances, and transaction counts match the
   preflight; and
8. reopen traffic only after the bridge smoke suite passes.

A database restore is required only if an unauthorized write changed financial
records. The plan deliberately avoids such writes.

## External Consumer Mitigation

Repository-local searches cannot prove that no external application consumes
Instruction. Before publishing the shim:

- inventory package-registry downloads and known installations;
- search organization repositories for Composer and namespace usage;
- identify consumers of the legacy HTTP route;
- publish an upgrade guide and support dates;
- provide a compatibility test application for downstream maintainers; and
- keep the last full Instruction release installable.

Unknown consumers are treated as a release risk, not as evidence that the
package is unused.

## Completion Criteria

The acquisition is complete only when:

- x-commerce is the only source of new commercial catalog and quote authority;
- x-change owns all required runtime and historical compatibility behavior;
- x-change installs, migrates, boots, caches, and tests without Instruction;
- the deprecated Instruction shim installs without a dependency cycle;
- all financial preflight and postflight measures reconcile;
- the current and legacy pricing contracts pass independently;
- every mandatory package and host suite passes;
- downstream consumers have completed or scheduled migration;
- no temporary dual-write or dual-route mechanism remains; and
- permanent persistent-identity compatibility is documented and tested.

Completion does not authorize renaming the legacy model FQCN or dropping legacy
tables. Those would be separate future migrations with independent business and
financial justification.

## Immediate Next Slice

The first implementation slice is characterization only:

1. add the current x-change pricing golden master;
2. port the legacy evaluator and endpoint fixtures without changing behavior;
3. add a historical wallet-identity fixture using the old FQCN;
4. add concrete allocator, collection, snapshot, and report tests;
5. add fresh-install and legacy-upgrade migration fixtures;
6. add lifecycle idempotence and destination-preservation tests;
7. add the clean dependency-graph smoke test; and
8. rerun the complete Instruction, x-commerce, x-change, and host suites.

No Composer dependency is removed in this slice.
