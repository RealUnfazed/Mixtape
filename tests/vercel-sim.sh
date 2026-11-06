#!/usr/bin/env bash
# Runs the app the way Vercel runs it and checks what is special there: Vercel's environment variables,
# api/index.php as the entry point, the proxy's forwarded headers, emulated prepared statements (what a PgBouncer
# pool needs) and form tokens that work on any instance. It is a simulation on your own machine, not Vercel itself.
#
# Needs bash, curl, php and a database with db/schema.sql applied (DATABASE_URL or .env, as for the normal app):
#   tests/vercel-sim.sh
# It starts two servers on ports 8099 and 8100 and stops them again. Test mixtapes are deleted at the end.
set -euo pipefail
cd "$(dirname "$0")/.."

A=http://127.0.0.1:${PORT:-8099}
B=http://127.0.0.1:$(( ${PORT:-8099} + 1 ))
LOG=$(mktemp)
PIDS=()
made=()

ok() { printf '  ok    %s\n' "$1"; }
bad() { printf '  FAIL  %s\n' "$1"; exit 1; }
field() { grep -o "\"$1\":\(\"[^\"]*\"\|[^,}]*\)" | head -1 | sed -e "s/^\"$1\"://" -e 's/^"//' -e 's/"$//'; }

remove_test_mixtapes() {
  local url path page token csrf
  for url in ${made[@]+"${made[@]}"}; do
    path=${url#*/m/}                                   # <id>/manage/<token>
    page=$(curl -sS "$A/m/$path") || continue
    token=$(grep -o 'name="token" value="[^"]*"' <<<"$page" | cut -d'"' -f4)
    csrf=$(grep -o 'name="csrf" value="[^"]*"' <<<"$page" | cut -d'"' -f4)
    curl -sS -o /dev/null -X POST -d "token=$token&csrf=$csrf" "$A/m/${path%%/*}/delete" || true
  done
}
stop() {
  remove_test_mixtapes
  for pid in ${PIDS[@]+"${PIDS[@]}"}; do kill "$pid" 2>/dev/null || true; done
  rm -f "$LOG"
}
trap stop EXIT

start_server() { # start_server <port>
  VERCEL=1 VERCEL_ENV=production VERCEL_REGION=iad1 VERCEL_DEPLOYMENT_ID=dpl_localtest0001 \
  DB_EMULATE_PREPARES=1 CREATE_LIMIT_PER_HOUR=3 APP_SECRET=vercel-sim-secret \
    php -S "127.0.0.1:$1" -t public tests/vercel-router.php >>"$LOG" 2>&1 &
  PIDS+=("$!")
  for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$1/" && return 0; sleep 0.1; done
  cat "$LOG"; bad "the server on port $1 did not start"
}
start_server "${A##*:}"
start_server "${B##*:}"

echo "Everything the normal smoke test checks, against the simulated function"
if smoke=$(BASE=$A tests/smoke.sh 2>&1); then
  ok "the smoke test passes ($(grep -c '^  ok' <<<"$smoke") checks)"
else
  printf '%s\n' "$smoke"
  bad "the smoke test failed"
fi

payload='{"tapeColor":"clover","stickers":["bow"],"toName":"A","fromName":"B","letter":"Hi","tracks":[{"videoId":"dQw4w9WgXcQ","title":"One","artist":"X"}]}'
csrf_from() { curl -sS "$1/new" | grep -o 'data-csrf="[^"]*"' | head -1 | cut -d'"' -f2; }
create() { # create <base> <csrf> [curl options]  ->  sets $code and $body, remembers the private link for cleanup
  local out
  out=$(curl -sS -w '\n%{http_code}' -H "X-CSRF-Token: $2" -H 'Content-Type: application/json' -d "$payload" "${@:3}" "$1/api/mixtapes")
  code=${out##*$'\n'}
  body=${out%$'\n'*}
  if [ "$code" = 201 ]; then made+=("$(printf '%s' "$body" | field manageUrl)"); fi
}
# Documentation addresses (RFC 5737), random per run so earlier runs do not count against the limit.
ip() { echo "203.0.113.$(( RANDOM % 250 + 1 ))"; }

echo "Share links behind Vercel's proxy"
t=$(csrf_from "$A")
create "$A" "$t" -H 'X-Forwarded-Proto: https' -H "X-Vercel-Forwarded-For: $(ip)" -H 'Host: mixtape.example.com'
[[ "$code" = 201 && "$(printf '%s' "$body" | field url)" == https://mixtape.example.com/m/* ]] && ok "the share link has https and the host the visitor used" || bad "share link: $code $body"
[[ "$(printf '%s' "$body" | field manageUrl)" == https://mixtape.example.com/m/*/manage/* ]] && ok "so does the private link" || bad "private link: $body"

echo "The rate limit counts visitors by the address Vercel reports (3 an hour in this test)"
mine=$(ip)
codes=""
for _ in 1 2 3 4; do create "$A" "$t" -H "X-Vercel-Forwarded-For: $mine"; codes="$codes$code "; done
[ "$codes" = "201 201 201 429 " ] && ok "the fourth mixtape from one address is refused" || bad "status codes were: $codes"
create "$A" "$t" -H "X-Vercel-Forwarded-For: $(ip).9"
[ "$code" = 201 ] && ok "another address is not affected" || bad "another address: $code"
create "$A" "$t" -H "X-Vercel-Forwarded-For: $mine" -H 'X-Forwarded-For: 192.0.2.1, 192.0.2.2'
[ "$code" = 429 ] && ok "sending your own X-Forwarded-For does not dodge it" || bad "spoofed header: $code"

echo "Tokens do not depend on which instance answers"
other=$(csrf_from "$B")
create "$A" "$other" -H "X-Vercel-Forwarded-For: $(ip)"
[ "$code" = 201 ] && ok "a token from one server is accepted by another" || bad "token from another instance: $code"
create "$A" "${other}x" -H "X-Vercel-Forwarded-For: $(ip)"
[ "$code" = 403 ] && ok "a damaged token is refused" || bad "damaged token: $code"

echo "Assets and headers"
curl -sS "$A/" | grep -q '/assets/app.css?v=dpl_localtes' && ok "asset URLs carry the deployment id" || bad "asset version missing"
headers=$(curl -sS -D - -o /dev/null "$A/new")
grep -qi '^set-cookie' <<<"$headers" && bad "the builder set a cookie" || ok "no cookie is set"
grep -qi "^content-security-policy:.*default-src 'self'" <<<"$headers" && ok "the Content-Security-Policy header is sent" || bad "no CSP header"

echo
echo "All Vercel checks passed."
