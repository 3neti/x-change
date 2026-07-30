# Campaign Worksheet Import UI/UX

## Purpose

Campaign import turns an ordinary CSV or XLSX beneficiary list into a reviewed
worksheet draft. Import is deliberately a staging operation: uploading a file
does not authorize a campaign, issue a Pay Code, deliver a message, call a
provider, or move money.

## User workflow

The default path begins on `/x/cockpit/campaigns`:

1. Choose a CSV or XLSX beneficiary list.
2. Review the suggested Campaign name, purpose, and recipient method.
3. Inspect the ready rows and row-level validation errors.
4. Correct the mapping or recipient method, then recheck the rows when needed.
5. Select the valid rows to include and explicitly confirm whether invalid rows
   should stay out.
6. Create one draft Campaign containing only the selected valid rows.
7. Complete, freeze, and authorize the worksheet through the normal Campaign
   lifecycle.

No Campaign exists before step 6. Starting an empty Campaign remains available
as a secondary path for manual entry.

Only one unresolved import preview is shown at a time. The worksheet cannot be
frozen while valid staged rows remain unapplied.

The existing worksheet-level importer remains available for an owner who
deliberately needs to add another beneficiary file to an existing draft.

## Deterministic suggestions

Suggestions are explainable defaults, never authorization:

- payroll, salary, and employee file/header language suggests Payroll;
- ayuda, aid, assistance, relief, or benefit language suggests Assistance;
- bank or account columns suggest direct bank transfer;
- mobile or email columns suggest Pay Code distribution;
- when both destination families exist, the user must confirm the recipient
  method.

The classifier does not infer purpose from names, mobile numbers, account
numbers, remarks, or other beneficiary PII.

## Flexible file contract

The smallest useful file has two columns:

```csv
mobile,amount
09173011987,1000.00
```

Other supported columns include name, email, bank or wallet name, bank account,
remarks, external reference, and delivery preference. Headers are detected from
common human labels and can be remapped before applying.

Amounts are always entered in pesos and may have at most two decimal places.
Minor units remain an internal accounting representation and never appear in
the import file or manual beneficiary form.

For direct-bank worksheets:

- a mobile number without a bank column defaults to GCash;
- common institution names are resolved through `3neti/money-issuer`;
- ambiguous or unsupported names fail closed and remain visible for correction;
- InstaPay is selected below ₱50,000 and PesoNet at ₱50,000 or more, subject to
  the institution's supported rails.

## Safety and privacy

- Original files are not retained.
- Source and normalized rows are encrypted individually in staging storage.
- The uploaded filename and source manifest are encrypted.
- A content hash detects an owner replaying the same file; a replay resolves to
  the existing review or Campaign instead of creating a duplicate.
- Formulas are rejected; import never evaluates spreadsheet content.
- Files are bounded by size, row count, column count, and cell length.
- Blank files, duplicate headers, duplicate mappings, and overlapping active
  previews are rejected.
- Valid rows are included only when selected. Invalid rows are never silently
  skipped, coerced, or included; exclusion requires explicit confirmation.
- Reapplying an already applied row is idempotent.
- Intake conversion creates the Campaign, import history, and beneficiary rows
  in one database transaction.

## Visual contract

Import Beneficiary List is the primary Campaign-index action. Start Blank is a
secondary disclosure. The review opens as a focused responsive workspace using
the same compact Cockpit card language as Funding and Pay Codes:

- suggestions and beneficiary preview are primary;
- mapping is compact and secondary;
- Ready, Errors, and Value are visible at a glance;
- the preview table remains horizontally scrollable on narrow screens;
- row inclusion is reversible until Campaign creation;
- manual Add Beneficiary remains available inside a draft for exceptions.

The status language describes draft preparation only. It must never imply that
beneficiaries have been paid or that a provider has accepted instructions.
