# Campaign Worksheet Import UI/UX

## Purpose

Campaign import turns an ordinary CSV or XLSX beneficiary list into a reviewed
worksheet draft. Import is deliberately a staging operation: uploading a file
does not authorize a campaign, issue a Pay Code, deliver a message, call a
provider, or move money.

## User workflow

1. Open a draft Campaign worksheet.
2. Choose a CSV or XLSX file.
3. Review the detected column mapping and row-level validation.
4. Correct the mapping or delivery default when necessary.
5. Add the valid rows to the draft explicitly.
6. Correct or discard the remaining invalid rows.
7. Freeze the complete worksheet and create its officer-authorization Pay Code.

Only one unresolved import preview is shown at a time. The worksheet cannot be
frozen while valid staged rows remain unapplied.

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
- Formulas are rejected; import never evaluates spreadsheet content.
- Files are bounded by size, row count, column count, and cell length.
- Blank files, duplicate headers, duplicate mappings, and overlapping active
  previews are rejected.
- Valid rows may be applied independently; invalid rows are never silently
  skipped or coerced.
- Reapplying an already applied row is idempotent.

## Visual contract

The import workspace is the first control on a draft worksheet. It uses the
same compact Cockpit card language as Funding and Pay Codes:

- upload and preview are primary;
- mapping is compact and secondary;
- Ready, Needs Attention, and Ready Value are visible at a glance;
- the preview table remains horizontally scrollable on narrow screens;
- manual Add Beneficiary remains available below for exceptions.

The status language describes draft preparation only. It must never imply that
beneficiaries have been paid or that a provider has accepted instructions.
