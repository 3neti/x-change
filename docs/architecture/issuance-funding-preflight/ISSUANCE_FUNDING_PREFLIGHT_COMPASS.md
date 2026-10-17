# Issuance Funding Preflight Compass

1. **Preflight is read-only.** Reactive requests never issue, debit, reserve, or create payment orders.
2. **Final submission is authoritative.** The server recalculates pricing and funding immediately before direct issuance or payment-order creation.
3. **One decision path.** LLM, Composer, POS, batch, public, and API surfaces must consume the same funding decision service rather than implement affordability rules independently.
4. **Ledger truth wins.** Client-funds availability comes from the authoritative balance read model used by the funding requirement, not a browser-provided balance.
5. **Pricing remains replay-safe.** Accepted offering references and snapshots are carried into final submission and checked again.
6. **Funding policy is explicit.** Full-amount funding remains the default until a separately approved shortfall or direct-debit policy change.
7. **No silent fallback.** If preflight is unavailable, the UI must show an indeterminate state; final submission still decides and reports the exact failure.
