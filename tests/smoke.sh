#!/usr/bin/env bash
# End-to-end check against a running server and database. Creates one mixtape and deletes it again.
#   Locally:    php -S 127.0.0.1:8080 -t public public/index.php     (in another terminal, with your .env)
#               tests/smoke.sh
#   Deployed:   BASE=https://your-project.vercel.app tests/smoke.sh
set -euo pipefail

BASE=${BASE:-http://127.0.0.1:8080}
BASE=${BASE%/}

pass() { printf '  ok    %s\n' "$1"; }
note() { printf '  note  %s\n' "$1"; }
fail() { printf '  FAIL  %s\n' "$1"; exit 1; }
expect() { # expect <description> <actual> <wanted>
  if [ "$2" = "$3" ]; then pass "$1"; else fail "$1 (got '$2', wanted '$3')"; fi
}
status() { curl -sS -o /dev/null -w '%{http_code}' "$@"; }
field() { grep -o "\"$1\":\(\"[^\"]*\"\|[^,}]*\)" | head -1 | sed -e "s/^\"$1\"://" -e 's/^"//' -e 's/"$//'; } # one flat JSON value, no jq or php needed
JSON=(-H 'Content-Type: application/json')

echo "Pages ($BASE)"
home=$(status "$BASE/")
[ "$home" = 401 ] && fail "home page answered 401: this looks like a Vercel preview URL behind Deployment Protection. Test your production domain."
expect "home page loads" "$home" 200
expect "unknown path is a 404" "$(status "$BASE/nothing-here")" 404
expect "unknown mixtape is a 404" "$(status "$BASE/m/zzzzzzzz")" 404
case "$(status "$BASE/index.php")" in
  302|404) pass "/index.php is not served as a page" ;;
  *) fail "/index.php should redirect or be a 404, not answer" ;;
esac
curl -sS "$BASE/index.php" | grep -q '<?php' && fail "PHP source is being served"
expect "assets are served" "$(status "$BASE/assets/app.css")" 200
for hidden in /.env /vercel.json /package.json /.vercelignore /src/app.php /src/Db.php /api/index.php /db/schema.sql /views/layout.php /README.md; do
  expect "$hidden is not served" "$(status "$BASE$hidden")" 404
done

echo "Creating"
builder_headers=$(mktemp)
trap 'rm -f "$builder_headers"' EXIT
csrf=$(curl -sS -D "$builder_headers" "$BASE/new" | grep -o 'data-csrf="[^"]*"' | head -1 | cut -d'"' -f2)
[ -n "$csrf" ] && pass "builder page hands out a CSRF token" || fail "no CSRF token on /new"
grep -qi '^set-cookie:' "$builder_headers" && fail "the builder should not set a cookie" || pass "the builder sets no cookie (tokens are signed, not stored)"
expect "create without a token is refused" "$(status "${JSON[@]}" -d '{}' "$BASE/api/mixtapes")" 403
expect "create with a made-up token is refused" "$(status -H "X-CSRF-Token: 1.$(printf '0%.0s' {1..32})" "${JSON[@]}" -d '{}' "$BASE/api/mixtapes")" 403
expect "invalid input is a 422" "$(status -H "X-CSRF-Token: $csrf" "${JSON[@]}" -d '{"letter":"","tracks":[]}' "$BASE/api/mixtapes")" 422
expect "bad YouTube link is a 422" "$(status -H "X-CSRF-Token: $csrf" "$BASE/api/lookup?url=https%3A%2F%2Fexample.com%2Fx")" 422

payload='{"tapeColor":"cherry","stickers":["bow","sprig"],"toName":"Mira","fromName":"Deniz","letter":"Hello there.\n\nThis one is for you.","tracks":[{"videoId":"dQw4w9WgXcQ","title":"Song One","artist":"Artist One"},{"videoId":"https://youtu.be/9bZkp7q19f0","title":"Song <b>Two</b>","artist":""}]}'
made=$(curl -sS -H "X-CSRF-Token: $csrf" "${JSON[@]}" -d "$payload" "$BASE/api/mixtapes")
id=$(printf '%s' "$made" | field id)
manage=$(printf '%s' "$made" | field manageUrl)
[[ "$id" =~ ^[a-z0-9]{8}$ ]] && pass "create returns an 8-character id ($id)" || fail "unexpected create response: $made"
expect "the share link uses this site's address" "$(printf '%s' "$made" | field url)" "$BASE/m/$id"

echo "Reading"
page=$(curl -sS "$BASE/m/$id")
grep -q 'A mixtape for Mira' <<<"$page" && pass "title uses the recipient's name" || fail "title missing"
grep -q 'This one is for you.' <<<"$page" && pass "letter is on the page" || fail "letter missing"
grep -q 'data-tape="cherry"' <<<"$page" && pass "page takes the tape color" || fail "tape color missing"
grep -q 'Song &lt;b&gt;Two&lt;/b&gt;' <<<"$page" && pass "titles are HTML-escaped" || fail "title not escaped"
grep -q '<b>Two</b>' <<<"$page" && fail "raw HTML reached the page"
grep -q '"videoId":"9bZkp7q19f0"' <<<"$page" && pass "a pasted link was reduced to its video id" || fail "video id missing"
headers=$(curl -sS -D - -o /dev/null "$BASE/m/$id")
grep -qi '^x-robots-tag: noindex' <<<"$headers" && pass "mixtape pages ask not to be indexed" || fail "no noindex header"
grep -qi '^set-cookie:' <<<"$headers" && fail "viewing a mixtape should not set a cookie" || pass "viewing a mixtape sets no cookie"
expect "HEAD requests work too" "$(status -I "$BASE/m/$id")" 200

echo "Managing"
expect "wrong manage token is a 404" "$(status "$BASE/m/$id/manage/00000000000000000000000000000000")" 404
manage_page=$(curl -sS "$BASE/m/${manage#*/m/}")
grep -q 'Delete this mixtape' <<<"$manage_page" && pass "manage page loads with the private link" || fail "manage page missing"
token=$(grep -o 'name="token" value="[^"]*"' <<<"$manage_page" | cut -d'"' -f4)
csrf2=$(grep -o 'name="csrf" value="[^"]*"' <<<"$manage_page" | cut -d'"' -f4)
expect "delete without CSRF is refused" "$(status -X POST -d "token=$token" "$BASE/m/$id/delete")" 404
expect "the builder's token cannot delete" "$(status -X POST -d "token=$token&csrf=$csrf" "$BASE/m/$id/delete")" 404
expect "delete with the private link works" "$(status -X POST -d "token=$token&csrf=$csrf2" "$BASE/m/$id/delete")" 303
expect "deleted mixtape is gone" "$(status "$BASE/m/$id")" 404

echo "Title lookup (the server has to reach YouTube)"
lookup=$(curl -sS -H "X-CSRF-Token: $csrf" "$BASE/api/lookup?url=https%3A%2F%2Fyoutu.be%2FdQw4w9WgXcQ" || true)
if [ "$(printf '%s' "$lookup" | field found)" = true ]; then
  pass "titles are fetched ($(printf '%s' "$lookup" | field title))"
else
  note "no title came back from YouTube. Everything else works, people just type the titles."
fi

echo
echo "All passed."
