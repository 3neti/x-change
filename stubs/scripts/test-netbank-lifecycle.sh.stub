#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [ -f "./scripts/.xchange-lifecycle.env" ]; then
# shellcheck disable=SC1091
source "./scripts/.xchange-lifecycle.env"
fi

ISSUER_ID="${ISSUER_ID:-1}"
WALLET_ID="${WALLET_ID:-1}"
AMOUNT="${AMOUNT:-25}"
MOBILE="${MOBILE:-639171234567}"
BANK_CODE="${BANK_CODE:-GXCHPHM2XXX}"
ACCOUNT_NUMBER="${ACCOUNT_NUMBER:-09173011987}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-180}"
POLL_SECONDS="${POLL_SECONDS:-10}"

echo "== X-Change Netbank lifecycle test =="
echo "issuer: $ISSUER_ID"
echo "wallet: $WALLET_ID"
echo "amount: $AMOUNT"
echo "mobile: $MOBILE"
echo "bank_code: $BANK_CODE"
echo "account_number: $ACCOUNT_NUMBER"
echo

echo "== Generating voucher =="
GEN_OUTPUT=$(php artisan xchange:paycode:generate \
--issuer="$ISSUER_ID" \
--wallet="$WALLET_ID" \
--amount="$AMOUNT" \
--json)

echo "$GEN_OUTPUT"
echo

CODE=$(php -r '
$data = json_decode(stream_get_contents(STDIN), true);
if (!is_array($data) || !isset($data["code"])) {
fwrite(STDERR, "Unable to parse voucher code\n");
exit(1);
}
echo $data["code"];
' <<< "$GEN_OUTPUT")

echo "Voucher code: $CODE"
echo

echo "== Submitting claim =="
CLAIM_OUTPUT=$(php artisan xchange:claim:submit "$CODE" \
--mobile="$MOBILE" \
--bank-code="$BANK_CODE" \
--account-number="$ACCOUNT_NUMBER" \
--json)

echo "$CLAIM_OUTPUT"
echo

echo "== Initial disbursement check =="
php artisan xchange:disbursement:check "$CODE" --json
echo

START_TIME=$(date +%s)

while true; do
NOW=$(date +%s)
ELAPSED=$((NOW - START_TIME))

if [ "$ELAPSED" -ge "$TIMEOUT_SECONDS" ]; then
echo "Timed out after ${TIMEOUT_SECONDS}s waiting for final status."
exit 2
fi

echo "== Syncing disbursement status (elapsed: ${ELAPSED}s) =="
STATUS_OUTPUT=$(php artisan xchange:disbursement:check "$CODE" --sync --json)
echo "$STATUS_OUTPUT"
echo

STATUS=$(php -r '
$data = json_decode(stream_get_contents(STDIN), true);
if (!is_array($data)) {
fwrite(STDERR, "Unable to parse status payload\n");
exit(1);
}
echo $data["current_status"] ?? "";
' <<< "$STATUS_OUTPUT")

if [ "$STATUS" = "succeeded" ]; then
echo "Lifecycle test passed: disbursement succeeded."
exit 0
fi

if [ "$STATUS" = "failed" ]; then
echo "Lifecycle test failed: disbursement failed."
exit 1
fi

echo "Still pending. Sleeping ${POLL_SECONDS}s..."
echo
sleep "$POLL_SECONDS"
done
