# Stored-Value Collector Commissioning

This release permits a collector-authorized spend only when the Pay Code has a frozen `collection_authorization` instruction and the collector is explicitly commissioned. Older Pay Codes continue to require the holder OTP in the collection flow. Operations checkout always requires the holder OTP.

## Configuration

Set `XCHANGE_STORED_VALUE_COLLECTION_FIXED_AMOUNT_MINOR` to a positive PHP minor-unit amount for a fixed fare, or `0` for operator-entered amounts. The server enforces the same fare on Cockpit, operations, and Partner API requests.

For Cockpit staff collection, `XCHANGE_STORED_VALUE_STAFF_COLLECTION_ENABLED=true` and positive `XCHANGE_STORED_VALUE_STAFF_COLLECTION_MAXIMUM_AMOUNT_MINOR` and `XCHANGE_STORED_VALUE_STAFF_COLLECTION_DAILY_AMOUNT_MINOR` are necessary but not sufficient. The host must explicitly grant the `x-change.stored-value.collect` Gate ability to each approved authenticated operator. No default grant is supplied. Review staff grants and limits before enabling this path.

`XCHANGE_STORED_VALUE_STAFF_COLLECTION_DESTINATION=collector` credits the operator's Client Funds position. Set it to `commercial_principal` to credit the commissioned commercial principal's Client Funds position instead, while retaining the authenticated operator as the collection actor. The receipt and journal record the destination principal reference. A host choosing the commercial destination must have an active commercial principal and a compatible treasury connection. Public Reusable Balance issuance is offered only when issuance and a collection destination are commissioned; the funded order must still be paid before a Pay Code is issued.

For Partner API collection, use an active client with the `stored-value:spend` scope and an enabled `stored_value_spend` mandate containing PHP, a positive maximum amount, and a positive daily amount. Production clients additionally require an activated production mandate.

## Issuance and compatibility

Newly issued stored-value instructions freeze the collection authorization mode alongside `otp_required_above`. Zero means a commissioned collector may collect without holder OTP. A positive threshold permits collector authorization at or below that threshold and requires holder OTP above it. The policy is read from the issued voucher at collection start and checked again at commit. An existing voucher without this schema remains on the holder-OTP path.

The migration adds `authorization_method` with the `holder_otp` default so existing collection records retain their meaning. Receipts and `stored_value.collection.committed` journal events identify the actual method. An OTP-free receipt never asserts OTP verification.

## Acceptance

Before enabling the path on an instance, verify an approved collector's fixed or entered amount, successful draw and receipt, repeated commit, revoked collector, exceeded limit, insufficient balance, an older OTP-required code, and the operations OTP path. Verify that a public or unauthenticated scanner cannot read a balance. Use provider fixtures for lifecycle checks; do not use a live transfer as a test.
