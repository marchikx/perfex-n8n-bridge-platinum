#!/usr/bin/env bash
# Does the verification node work inside a real n8n, or only inside our harness?
#
# tests/verify-node.test.js runs the node's real code, but it hands that code a
# stub for getBinaryDataAsync - it returns the item's own binary property. So the
# suite proves the node's logic against *our belief* about what n8n gives a
# Function node. A wrong belief about exactly that is what shipped in March:
# the node read $input.first().headers, n8n puts them under json.headers, and it
# threw on every request for five months. Eighteen green cases would not have
# caught it, because the harness would have been wrong in the same direction.
#
# So this asks n8n. It boots the real image, imports the published webhook node
# and the published verification node verbatim, sends a genuinely signed HTTP
# request at the real webhook URL, and reads what comes back.
#
# No account is created and no password is typed: import and activation go
# through the n8n CLI inside the container, and a production webhook is public
# by design. The project forbids the agent creating accounts even on throwaway
# local instances, and the older stand in n8n-kit-runner-check does POST
# /rest/owner/setup - that approach is deliberately not reused here.
#
# Exit 0 = the node behaves in n8n the way the harness says it does.
set -uo pipefail

N8N_IMAGE="${N8N_IMAGE:-n8nio/n8n:1.62.1}"
SECRET="e2e-secret-not-a-real-one"
BASE="http://localhost:5678"
WEBHOOK="$BASE/webhook/perfex-e2e"
fails=0

say() { printf '\n=== %s\n' "$*"; }

say "building a workflow from the PUBLISHED nodes"
# The webhook node's options and the verification node's code are lifted out of
# n8n_blueprint.json unchanged. Only the wiring is ours: the shipped workflow
# routes through Slack, which would need credentials and has nothing to do with
# the question being asked.
node ci/build-e2e-workflow.js > /tmp/e2e-workflow.json || { echo "build failed"; exit 1; }
echo "workflow: $(wc -c < /tmp/e2e-workflow.json) bytes"

say "starting $N8N_IMAGE"
docker rm -f n8n >/dev/null 2>&1
docker run -d --name n8n -p 5678:5678 \
  -e N8N_ENCRYPTION_KEY=e2e-encryption-key-0123456789 \
  -e PERFEX_HMAC_SECRET="$SECRET" \
  -e NODE_FUNCTION_ALLOW_BUILTIN=crypto \
  -e N8N_SECURE_COOKIE=false \
  -e N8N_DIAGNOSTICS_ENABLED=false \
  -e GENERIC_TIMEZONE=UTC \
  "$N8N_IMAGE" >/dev/null || { echo "docker run failed"; exit 1; }

say "waiting for n8n"
for i in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/healthz" || true)
  [ "$code" = "200" ] && break
  sleep 5
done
echo "healthz http=$code"
[ "$code" = "200" ] || { docker logs n8n 2>&1 | tail -40; exit 1; }

say "importing through the CLI (no owner setup, no password)"
docker cp /tmp/e2e-workflow.json n8n:/tmp/wf.json
docker exec n8n n8n import:workflow --input=/tmp/wf.json || { docker logs n8n 2>&1 | tail -30; exit 1; }
# list:workflow prints a settings banner before the rows, and runs 34324252208
# and 34324424610 both parsed that banner as the id. --onlyId prints ids alone;
# the filter keeps only lines that are entirely id-shaped, so a banner cannot
# survive it even if the flag is missing and the banner comes through anyway.
WFID=$(docker exec n8n n8n list:workflow --onlyId 2>/dev/null | tr -d '\r' \
        | grep -E '^[A-Za-z0-9_-]+$' | tail -1)
if [ -z "$WFID" ]; then
  WFID=$(docker exec n8n n8n list:workflow 2>/dev/null | tr -d '\r' \
          | grep -E '^[A-Za-z0-9_-]+\|' | tail -1 | cut -d'|' -f1)
fi
echo "workflow id=$WFID"
case "$WFID" in ''|*[!A-Za-z0-9_-]*) echo "id does not look like an id: '$WFID'"; docker exec n8n n8n list:workflow; exit 1;; esac

docker exec n8n n8n update:workflow --id="$WFID" --active=true || { echo "activate failed"; exit 1; }

say "restarting so the webhook registers"
docker restart n8n >/dev/null
for i in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/healthz" || true)
  [ "$code" = "200" ] && break
  sleep 5
done
sleep 5

# --- the actual question -----------------------------------------------------
post() { # body, signature -> prints the response body
  curl -s -w ' HTTPCODE=%{http_code}' -X POST "$WEBHOOK" \
    -H 'content-type: application/json' \
    -H "x-perfex-signature: $2" \
    -H 'x-perfex-event: invoice_paid' \
    --data-binary "$1"
}

# A rejection only counts when the request actually reached the node. In run
# 34324252208 the workflow was never activated, every POST 404ed, and both
# forged-signature cases still printed ok - they passed for the wrong reason.
# So a rejection must carry the verifier's own answer, not merely lack an
# acceptance. This function was referenced but never defined until now, so runs
# up to 34324647318 asserted nothing at all on cases 2 and 3.
expect_rejected() { # label, output
  if echo "$2" | grep -q 'HTTPCODE=200' && echo "$2" | grep -q '"verified":false'; then
    echo "  ok - reached the node and was rejected"
  elif echo "$2" | grep -q '"verified":true'; then
    echo "  FAIL - $1 was ACCEPTED"; fails=$((fails+1))
  else
    echo "  FAIL - never reached the verifier (no verified:false with HTTP 200)"; fails=$((fails+1))
  fi
}
sign() { printf '%s' "$1" | openssl dgst -sha256 -hmac "$SECRET" -r | cut -d' ' -f1; }

TS=$(date +%s)
BODY="{\"event\":\"invoice_paid\",\"timestamp\":$TS,\"data\":{\"id\":42}}"

say "1. a genuinely signed request"
OUT=$(post "$BODY" "$(sign "$BODY")")
echo "$OUT"
if echo "$OUT" | grep -q '"verified":true'; then
  echo "  ok - accepted inside real n8n"
else
  echo "  FAIL - the harness says this passes; n8n does not"
  fails=$((fails+1))
fi

say "2. the same body with a forged signature"
OUT=$(post "$BODY" "$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac wrong-secret -r | cut -d' ' -f1)")
echo "$OUT"
expect_rejected "a forged signature" "$OUT"

say "3. a request signed with the secret that used to be published in the file"
OUT=$(post "$BODY" "$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac 'your-hmac-secret-here' -r | cut -d' ' -f1)")
echo "$OUT"
expect_rejected "the old published default" "$OUT"

say "n8n logs"
docker logs n8n 2>&1 | tail -30

say "result: $fails failure(s)"
exit $fails
