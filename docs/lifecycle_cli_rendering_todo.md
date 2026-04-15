# TODO: Improve Lifecycle CLI Non-JSON Rendering

## Goal
Enhance the readability and usefulness of `php artisan xchange:lifecycle:run` (non-JSON mode).

---

## 1. Remove Duplicate Tariff Display
- [ ] Remove repeated `Estimated Tariff` and `Charge Lines` block in final summary
- [ ] OR rename second block to `Final Charged Tariff` if retained intentionally

---

## 2. Improve Wallet Transaction Table Readability
- [ ] Truncate `idempotency_key` (e.g., `lifecycle-a383b566…`)
- [ ] Consider removing or renaming `Voucher` column (often `n/a`)
- [ ] Keep columns concise for terminal display

---

## 3. Add Flow Summary Line
- [ ] Add a one-line summary before transactions table:
  - Example: `Flow Impact: ₱15.00 tariff charged, payout requested`

---

## 4. Enhance Final Summary Details
- [ ] Include `reference_number` (if available)
- [ ] Optionally include `reconciliation_id`
- [ ] Ensure final status block is clean and grouped

---

## 5. Formatting Consistency
- [ ] Ensure all monetary values use `Number::currency()`
- [ ] Align spacing and indentation across sections

---

## 6. Optional Enhancements (Future)
- [ ] Colorize status (success, pending, failed)
- [ ] Add elapsed time summary
- [ ] Add verbose mode for deeper diagnostics

---

## Status
- Current output is already functional and readable
- These improvements are polish-level enhancements

---

## Priority
Low (UX refinement)

