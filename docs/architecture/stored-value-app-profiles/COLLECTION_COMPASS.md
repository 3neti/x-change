# Stored-Value Collection Compass

1. A Pay Code's frozen issuance instruction decides whether the holder OTP is required. Existing vouchers are not reinterpreted by a new default.
2. No-OTP means collector authorization, not absence of authorization. Collector identity, scope, limits, and the presented Pay Code must all be checked server-side.
3. A collection is identified by one stable reference and idempotency key. Retrying that reference cannot debit twice.
4. The server owns the fare and final amount. A fixed fare cannot be changed by a browser or terminal request.
5. Balance and collector limits are checked again under the commit lock. Insufficient funds or a revoked collector produces no draw.
6. The execution context, receipt, and x-journal distinguish OTP proof from collector-only authority. They never claim an OTP occurred when it did not.
7. Showing a balance is a protected read. A public scanner or unauthenticated visitor cannot enumerate stored-value balances.
8. Reconciliation follows the existing Treasury allocation and voucher execution contracts; profiles do not implement a second wallet.
