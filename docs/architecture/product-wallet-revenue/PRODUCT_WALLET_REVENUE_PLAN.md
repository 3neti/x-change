# Product Wallet Revenue Plan

Last updated: 2026-10-19

Use the accepted x-commerce quote as the sole pricing authority. After funding
and issuance are verified, route only the Product revenue lines to instruction
Product wallets through `ProductWalletTransferContract`. Keep Pay Code
principal and all non-Product waterfall lines in Treasury positions.

## Required behavior

- use `amount_minor` from the accepted quote;
- attach sale, quote, instruction, and idempotency references;
- preserve exactly-once behavior through the commercial allocation record;
- support collection and reversal without mutating historical transfers; and
- reconcile Product wallet balances with commercial allocation facts.

## Gate status

The current gate is shadow-only. It reconciles the accepted allocation plan
with posted Treasury allocation facts and reserves an immutable reversal-ledger
schema. No wallet movement is enabled until the bridge and reversal execution
tests run together against a commissioned database.

## Deprecation

The direct `transferFloat()` call in `InstructionRevenueAllocatorService` is
deprecated for core issuance allocation and is replaced by the wallet contract.
