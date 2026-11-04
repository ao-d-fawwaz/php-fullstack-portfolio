#!/usr/bin/env bash
# Unit test for the calculator + end-to-end API test (PHP built-in server, throwaway SQLite DB).
# Usage: bash tests/run_tests.sh
set -u
cd "$(dirname "$0")/.."
echo "Calculator"; php tests/CalculatorTest.php || exit 1
DB=$(mktemp -d)/test.sqlite
export DB_DSN="sqlite:$DB" DB_USER="" DB_PASS=""
php tests/sql.php "$DB" --schema database/schema.sqlite.sql
php database/create_user.php sales secret123 >/dev/null
PORT=8767; BASE="http://127.0.0.1:$PORT"
php -S 127.0.0.1:$PORT -t public >/tmp/quote_server.log 2>&1 & SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 1
PASS=0; FAIL=0
check() { if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected $3, got $2)"; fi; }
code() { curl -s -o /tmp/body -w '%{http_code}' "$@"; }
JAR=$(mktemp)
T=$(curl -s -c $JAR -b $JAR $BASE/login.php | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -c $JAR -b $JAR -d "csrf_token=$T&username=sales&password=secret123" $BASE/login.php
CSRF=$(curl -s -b $JAR $BASE/index.php | grep -o 'const CSRF = "[^"]*"' | sed 's/.*= "//;s/"//')
H=(-b $JAR -H "X-CSRF-Token: $CSRF" -H 'Content-Type: application/json')
Q='{"tour_code":"CHN-06","tour_name":"Beijing & Shanghai <Classic>","period_start":"2027-03-01","period_end":"2027-03-06","min_pax":10,"foc":1,"rate_usd":16000,"rate_rmb":2200,"pic":"Rina","final_selling":9000000,
"items":[{"type":"main","description":"Hotel 4N","currency":"USD","nett_vendor":2000,"divide_by":0,"profit_percent":20},
{"type":"main","description":"Flight","currency":"IDR","nett_vendor":4500000,"divide_by":1,"profit_percent":10},
{"type":"variable","description":"Guide","currency":"RMB","nett_vendor":600,"qty_days":5},
{"type":"optional","description":"Night show","currency":"RMB","nett_vendor":300,"divide_by":1,"profit_percent":15}]}'

echo "API"
check "not logged in -> 401" "$(code $BASE/api/quotations.php)" 401
check "missing CSRF -> 403" "$(code -b $JAR -H 'Content-Type: application/json' -X POST -d "$Q" $BASE/api/quotations.php)" 403
check "live preview" "$(code "${H[@]}" -X POST -d "$Q" $BASE/api/calculate.php)" 200
check "preview net margin -130,000" "$(grep -c '"net_margin_per_pax":-130000' /tmp/body)" 1
check "preview does not save" "$(php tests/sql.php $DB 'select count(*) from quotations')" 0
check "create quotation" "$(code "${H[@]}" -X POST -d "$Q" $BASE/api/quotations.php)" 201
ID=$(grep -o '"id":[0-9]*' /tmp/body | head -1 | cut -d: -f2)
check "items stored in order" "$(php tests/sql.php $DB "select group_concat(description) from (select description from quotation_items order by position)")" "Hotel 4N,Flight,Guide,Night show"
TAMPERED=$(echo "$Q" | sed 's/"profit_percent":20}/"profit_percent":20,"selling":1,"margin":999999999}/')
check "client-sent totals are ignored" "$(code "${H[@]}" -X PUT -d "$TAMPERED" "$BASE/api/quotations.php?id=$ID"; grep -c '"selling_per_pax":8790000' /tmp/body)" 2001
check "list shows quotation" "$(code -b $JAR "$BASE/api/quotations.php?q=CHN"; grep -c 'CHN-06' /tmp/body)" 2001
check "search with no match" "$(code -b $JAR "$BASE/api/quotations.php?q=zzz"; grep -c 'CHN-06' /tmp/body)" 2000
BAD=$(echo "$Q" | sed 's/"period_end":"2027-03-06"/"period_end":"2027-02-01"/; s/"min_pax":10/"min_pax":0/; s/"currency":"USD"/"currency":"EUR"/')
check "invalid input -> 422" "$(code "${H[@]}" -X POST -d "$BAD" $BASE/api/quotations.php)" 422
check "3 field errors reported" "$(grep -o '"period_end"\|"min_pax"\|"items.0.currency"' /tmp/body | wc -l)" 3
check "needs a main item" "$(code "${H[@]}" -X POST -d '{"tour_code":"X1","tour_name":"x","period_start":"2027-01-01","period_end":"2027-01-02","min_pax":2,"rate_usd":1,"rate_rmb":1,"items":[]}' $BASE/api/quotations.php)" 422
check "update unknown id -> 404" "$(code "${H[@]}" -X PUT -d "$Q" "$BASE/api/quotations.php?id=999")" 404

echo "Print"
curl -s -b $JAR "$BASE/print.php?id=$ID" > /tmp/print.html
check "print shows final price" "$(grep -c 'Rp 9.000.000' /tmp/print.html)" 1
check "print hides nett vendor costs" "$(grep -c '4.500.000\|3.200.000' /tmp/print.html)" 0
check "tour name is HTML-escaped" "$(grep -c '&lt;Classic&gt;' /tmp/print.html)" 1
check "optional add-on listed" "$(grep -c 'Rp 759.000' /tmp/print.html)" 1

echo "Delete"
check "delete" "$(code "${H[@]}" -X DELETE "$BASE/api/quotations.php?id=$ID")" 200
check "items cascade-deleted" "$(php tests/sql.php $DB 'select count(*) from quotation_items')" 0
check "app page renders" "$(curl -s -b $JAR $BASE/index.php | grep -c 'Tour Quotation</h1>')" 1
check "no PHP errors in server log" "$(grep -ci 'fatal\|warning\|deprecated' /tmp/quote_server.log)" 0
echo; echo "passed: $PASS  failed: $FAIL"; [ $FAIL -eq 0 ]
