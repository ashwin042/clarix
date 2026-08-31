#!/usr/bin/env bash
#
# Verifies GET /api/v1/n8n/telegram/tasks against a *deployed* Clarix.
#
# The feature suite proves the rules; this proves the deploy. They fail in
# different ways: a route that exists locally and 404s on Railway, a key that
# does not match the one in n8n's credential store, a proxy that rewrites an
# error into an HTML page. None of those are visible from tests/.
#
# The one check worth reading twice is the per-unit one. task_code is unique
# per *unit*, not per agency, so the same code may legitimately exist in two
# units. If this script's "same code, other unit" case comes back with a match,
# the endpoint is scoping globally and the bot will refuse codes that are free.
#
# Usage:
#
#   BASE_URL=https://your-app.up.railway.app \
#   N8N_KEY=<the live shared key> \
#   ADMIN_CHAT=<a linked admin's telegram chat id> \
#   TASK_CODE=<a code that exists> \
#   UNIT_ID=<the unit that code lives in> \
#   OTHER_UNIT_ID=<another unit of the same agency> \
#   PM_CHAT=<a linked PM's chat id, optional> \
#   ./scripts/verify-n8n-task-read.sh
#
# Exits non-zero if any check fails.

set -uo pipefail

fail=0
pass=0

need() {
  if [ -z "${!1:-}" ]; then
    echo "missing required env var: $1" >&2
    exit 2
  fi
}

need BASE_URL
need N8N_KEY
need ADMIN_CHAT
need TASK_CODE
need UNIT_ID
need OTHER_UNIT_ID

ENDPOINT="${BASE_URL%/}/api/v1/n8n/telegram/tasks"

# Writes the body to $BODY and the status to $STATUS.
#
# Deliberately sends no Accept header unless asked. That is the point of
# several of the checks below: Laravel picks the shape of an error response
# from Accept, so a caller that omits it used to get a 302 to the homepage
# instead of a JSON error — which n8n cannot parse and which made the real
# failure invisible.
call() {
  local url="$1"; shift
  local tmp
  tmp="$(mktemp)"
  STATUS="$(curl -sS -o "$tmp" -w '%{http_code}' "$@" "$url")"
  BODY="$(cat "$tmp")"
  rm -f "$tmp"
}

check() {
  local label="$1" ok="$2" detail="${3:-}"
  if [ "$ok" = "1" ]; then
    printf '[ ok ] %s\n' "$label"
    pass=$((pass + 1))
  else
    printf '[FAIL] %s\n' "$label"
    [ -n "$detail" ] && printf '       %s\n' "$detail"
    fail=$((fail + 1))
  fi
}

# jq is not assumed. These are deliberately crude string probes so the script
# runs anywhere curl does.
json_num() { # json_num <body> <key>
  printf '%s' "$1" | tr -d ' \n' | sed -n "s/.*\"$2\":\([0-9-]*\).*/\1/p" | head -1
}
has_key() { printf '%s' "$1" | grep -q "\"$2\""; }
is_json() { printf '%s' "$1" | grep -qE '^\s*[\{\[]'; }

echo "verifying ${ENDPOINT}"
echo

# ── auth ─────────────────────────────────────────────────────────────────────

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}"
check "missing key -> 401" \
  "$([ "$STATUS" = "401" ] && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:200}"
check "missing key -> JSON, not a redirect or HTML" \
  "$(is_json "$BODY" && echo 1 || echo 0)" "body: ${BODY:0:200}"

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}" -H "X-N8n-Key: definitely-not-the-key"
check "wrong key -> 401" \
  "$([ "$STATUS" = "401" ] && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:200}"
check "wrong key -> JSON" \
  "$(is_json "$BODY" && echo 1 || echo 0)" "body: ${BODY:0:200}"

# ── the happy path, with no Accept header at all ─────────────────────────────

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}" -H "X-N8n-Key: ${N8N_KEY}"
check "valid key -> 200" \
  "$([ "$STATUS" = "200" ] && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:300}"
check "response carries tasks and count" \
  "$(has_key "$BODY" tasks && has_key "$BODY" count && echo 1 || echo 0)" "body: ${BODY:0:300}"
check "success is JSON without an Accept header" \
  "$(is_json "$BODY" && echo 1 || echo 0)" "body: ${BODY:0:200}"

broad_count="$(json_num "$BODY" count)"
echo "       (agency total visible to this chat: ${broad_count:-?})"

# ── task_code is unique per unit, not globally ───────────────────────────────

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}&task_code=${TASK_CODE}&unit_id=${UNIT_ID}" \
  -H "X-N8n-Key: ${N8N_KEY}"
own_unit="$(json_num "$BODY" count)"
check "known code in its own unit -> a match" \
  "$([ "${own_unit:-0}" -ge 1 ] 2>/dev/null && echo 1 || echo 0)" \
  "count=${own_unit:-?} status=${STATUS} body=${BODY:0:300}"

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}&task_code=${TASK_CODE}&unit_id=${OTHER_UNIT_ID}" \
  -H "X-N8n-Key: ${N8N_KEY}"
other_unit="$(json_num "$BODY" count)"
check "same code, other unit -> 200" \
  "$([ "$STATUS" = "200" ] && echo 1 || echo 0)" "got ${STATUS}"
# Not a hard failure: the code may legitimately exist in both units. It is
# flagged loudly because the usual cause is global rather than per-unit scoping.
if [ "${other_unit:-0}" = "0" ]; then
  check "same code, other unit -> no match (per-unit scoping)" 1
else
  echo "[ ?? ] same code, other unit returned ${other_unit} match(es)."
  echo "       Either that code genuinely exists in unit ${OTHER_UNIT_ID} too,"
  echo "       or the endpoint is scoping task_code globally. Check by hand."
fi

# ── no results is not an error ───────────────────────────────────────────────

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}&task_code=__no_such_code__" -H "X-N8n-Key: ${N8N_KEY}"
check "no results -> 200, not 404" \
  "$([ "$STATUS" = "200" ] && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:200}"
check "no results -> count 0" \
  "$([ "$(json_num "$BODY" count)" = "0" ] && echo 1 || echo 0)" "body: ${BODY:0:200}"
check "no results -> empty array" \
  "$(printf '%s' "$BODY" | tr -d ' ' | grep -q '"tasks":\[\]' && echo 1 || echo 0)" "body: ${BODY:0:200}"

# ── bad params answer in JSON too ────────────────────────────────────────────

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}&status=nonsense" -H "X-N8n-Key: ${N8N_KEY}"
check "unknown status -> 422" \
  "$([ "$STATUS" = "422" ] && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:200}"
check "unknown status -> JSON with errors" \
  "$(has_key "$BODY" errors && echo 1 || echo 0)" "body: ${BODY:0:200}"

call "${ENDPOINT}" -H "X-N8n-Key: ${N8N_KEY}"
check "missing chat_id -> 422 JSON" \
  "$([ "$STATUS" = "422" ] && is_json "$BODY" && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:200}"

call "${ENDPOINT}?chat_id=99999999999" -H "X-N8n-Key: ${N8N_KEY}"
check "unlinked chat -> 404 with linked:false" \
  "$([ "$STATUS" = "404" ] && has_key "$BODY" linked && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:200}"

# ── the page cap ─────────────────────────────────────────────────────────────

call "${ENDPOINT}?chat_id=${ADMIN_CHAT}" -H "X-N8n-Key: ${N8N_KEY}"
returned="$(printf '%s' "$BODY" | grep -o '"task_code"' | wc -l | tr -d ' ')"
check "a broad query returns at most one page" \
  "$([ "$returned" -le 50 ] && echo 1 || echo 0)" "returned ${returned} tasks"
echo "       (returned ${returned} of ${broad_count:-?}; count must be the total, not the page)"

# ── the PM ceiling, if a PM chat was supplied ────────────────────────────────

if [ -n "${PM_CHAT:-}" ]; then
  call "${ENDPOINT}?chat_id=${PM_CHAT}&unit_id=${OTHER_UNIT_ID}" -H "X-N8n-Key: ${N8N_KEY}"
  check "a PM naming another unit -> 403" \
    "$([ "$STATUS" = "403" ] && echo 1 || echo 0)" \
    "got ${STATUS} (expected 403 unless ${OTHER_UNIT_ID} is this PM's own unit): ${BODY:0:200}"
  check "that refusal is JSON" \
    "$(is_json "$BODY" && echo 1 || echo 0)" "body: ${BODY:0:200}"

  call "${ENDPOINT}?chat_id=${PM_CHAT}" -H "X-N8n-Key: ${N8N_KEY}"
  pm_count="$(json_num "$BODY" count)"
  check "a PM's own query -> 200" \
    "$([ "$STATUS" = "200" ] && echo 1 || echo 0)" "got ${STATUS}: ${BODY:0:200}"
  echo "       (this PM reaches ${pm_count:-?} tasks; it should equal their unit's count on the board)"
else
  echo "[skip] PM ceiling checks (set PM_CHAT to a linked PM's chat id to run them)"
fi

echo
echo "${pass} passed, ${fail} failed"
exit $([ "$fail" -eq 0 ] && echo 0 || echo 1)
