#!/usr/bin/env bash
# Runnable examples against php artisan serve (http://127.0.0.1:8000).
set -euo pipefail

BASE="${BASE:-http://127.0.0.1:8000}"
SECRET="${MOCK_INSURER_SHARED_SECRET:-change-me-local-only}"
ROOT="$(cd "$(dirname "$0")" && pwd)"

echo "== Create (expect 201, premium 225.00) =="
CREATE=$(curl -sS -X POST "$BASE/api/policy-requests" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'X-Mock-Scenario: SUCCESS' \
  -d @"$ROOT/create-policy-request.json")
echo "$CREATE"

ID=$(php -r 'echo json_decode(file_get_contents("php://stdin"))->data->id ?? "";' <<<"$CREATE")
if [[ -z "$ID" ]]; then
  echo "Could not parse request id" >&2
  exit 1
fi

echo "== Duplicate create (expect 200) =="
curl -sS -X POST "$BASE/api/policy-requests" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d @"$ROOT/create-policy-request.json"
echo

echo "== Show =="
curl -sS "$BASE/api/policy-requests/$ID" -H 'Accept: application/json'
echo

echo "== Submit (expect ISSUED) =="
curl -sS -X POST "$BASE/api/policy-requests/$ID/submit" -H 'Accept: application/json'
echo

echo "== List =="
curl -sS "$BASE/api/policy-requests?per_page=10&state=ISSUED" -H 'Accept: application/json'
echo

echo "== Invalid create (expect 422) =="
curl -sS -X POST "$BASE/api/policy-requests" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"plan_code":"GOLD"}'
echo

# Delayed-callback sample: create with DELAYED_CALLBACK, submit, then sign a callback.
# BODY='{"provider_event_id":"evt-001","provider_reference":"PRV-...","policy_request_id":"'"$ID"'","event_type":"ISSUED","policy_number":"POL-FROM-CALLBACK"}'
# SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')
# curl -sS -X POST "$BASE/api/mock-insurer/callback" -H 'Content-Type: application/json' -H "X-Mock-Insurer-Signature: $SIG" -d "$BODY"
