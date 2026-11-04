#!/usr/bin/env bash
# End-to-end API test against PHP's built-in server and a throwaway SQLite database.
# Usage: bash tests/run_tests.sh
set -u
cd "$(dirname "$0")/.."
DB=$(mktemp -d)/test.sqlite
export DB_DSN="sqlite:$DB" DB_USER="" DB_PASS="" JWT_SECRET="test-secret-$(date +%s)" RATE_LIMIT_MAX=1000 CORS_ORIGINS="http://allowed.test"
php tests/sql.php "$DB" --schema database/schema.sqlite.sql
php tests/sql.php "$DB" --schema database/seed.sql
php database/create_user.php admin admin@example.com AdminPass123 admin >/dev/null
php database/create_user.php sari sari@example.com StaffPass123 staff >/dev/null
PORT=8766; BASE="http://127.0.0.1:$PORT"
php -S 127.0.0.1:$PORT -t public public/index.php >/tmp/travel_server.log 2>&1 & SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 1
PASS=0; FAIL=0
check() { if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected $3, got $2)"; fi; }
code() { curl -s -o /tmp/body -w '%{http_code}' "$@"; }
J=(-H 'Content-Type: application/json')
token() { curl -s "${J[@]}" -d "{\"username\":\"$1\",\"password\":\"$2\"}" $BASE/api/auth/login | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"] ?? "";'; }
DEP=$(date -d '+30 days' +%Y-%m-%d); EXP=$(date -d '+3 years' +%Y-%m-%d); EXP_SHORT=$(date -d '+60 days' +%Y-%m-%d)
booking() { echo "{\"customer_name\":\"Budi Santoso\",\"phone\":\"+62 812 3456 7890\",\"email\":\"budi@example.com\",\"departure_date\":\"$DEP\",\"pax\":$1,\"passport_number\":\"c1234567\",\"passport_expiry\":\"$2\",\"product_code\":\"$3\"}"; }

echo "Auth"
check "wrong password -> 401" "$(code "${J[@]}" -d '{"username":"admin","password":"nope"}' $BASE/api/auth/login)" 401
check "unknown user same 401" "$(code "${J[@]}" -d '{"username":"ghost","password":"nope"}' $BASE/api/auth/login)" 401
check "invalid JSON -> 400" "$(code "${J[@]}" -d 'not json' $BASE/api/auth/login)" 400
ADMIN=$(token admin AdminPass123); STAFF=$(token sari StaffPass123)
check "login returns JWT" "$(echo $ADMIN | tr -cd . | wc -c)" 2
check "no token -> 401" "$(code $BASE/api/products)" 401
check "tampered token -> 401" "$(code -H "Authorization: Bearer ${ADMIN}x" $BASE/api/products)" 401
EXPIRED=$(php -r 'require "src/Core/Jwt.php"; echo App\Core\Jwt::encode(["sub"=>1,"username"=>"admin","role"=>"admin","exp"=>time()-10], getenv("JWT_SECRET"));')
check "expired token -> 401" "$(code -H "Authorization: Bearer $EXPIRED" $BASE/api/products)" 401
A=(-H "Authorization: Bearer $ADMIN"); S=(-H "Authorization: Bearer $STAFF")
check "staff cannot register users -> 403" "$(code "${S[@]}" "${J[@]}" -d '{"username":"x1","email":"x@example.com","password":"Password1"}' $BASE/api/auth/register)" 403
check "admin registers staff" "$(code "${A[@]}" "${J[@]}" -d '{"username":"dewi","email":"dewi@example.com","password":"Password1"}' $BASE/api/auth/register)" 201
check "duplicate username -> 409" "$(code "${A[@]}" "${J[@]}" -d '{"username":"dewi","email":"dewi@example.com","password":"Password1"}' $BASE/api/auth/register)" 409
check "weak password -> 422" "$(code "${A[@]}" "${J[@]}" -d '{"username":"eko","email":"eko@example.com","password":"short"}' $BASE/api/auth/register)" 422

echo "Products"
code "${S[@]}" $BASE/api/products >/dev/null
check "lists only active products" "$(grep -o '"code"' /tmp/body | wc -l)" 4
check "product by code" "$(code "${S[@]}" $BASE/api/products/JPN-07)" 200
check "unknown product -> 404" "$(code "${S[@]}" $BASE/api/products/XXX)" 404

echo "Bookings"
check "create booking (pax 2)" "$(code "${S[@]}" "${J[@]}" -d "$(booking 2 $EXP JPN-07)" $BASE/api/bookings)" 201
check "ledger total = 2 x 18,500,000" "$(grep -c '"total_amount":37000000' /tmp/body)" 1
check "passport number normalised" "$(grep -c '"passport_number":"C1234567"' /tmp/body)" 1
BID=$(grep -o '"id":[0-9]*' /tmp/body | head -1 | cut -d: -f2)
check "passport expiring <6 months -> 422" "$(code "${S[@]}" "${J[@]}" -d "$(booking 1 $EXP_SHORT KOR-05)" $BASE/api/bookings)" 422
check "inactive product -> 422" "$(code "${S[@]}" "${J[@]}" -d "$(booking 1 $EXP EUR-12)" $BASE/api/bookings)" 422
check "pax 0 -> 422" "$(code "${S[@]}" "${J[@]}" -d "$(booking 0 $EXP KOR-05)" $BASE/api/bookings)" 422
check "missing fields listed" "$(code "${S[@]}" "${J[@]}" -d '{"customer_name":"A"}' $BASE/api/bookings; grep -o '"Required"' /tmp/body | wc -l)" 4227
check "SQL injection in product code is just data" "$(code "${S[@]}" "${J[@]}" -d "$(booking 1 $EXP "JPN-07' OR '1'='1")" $BASE/api/bookings)" 422
check "failed bookings wrote nothing" "$(php tests/sql.php $DB 'select count(*) from bookings')" 1
check "show booking with ledger" "$(code "${S[@]}" $BASE/api/bookings/$BID; grep -c '"ledger":\[{' /tmp/body)" 2001
check "list with pagination meta" "$(code "${S[@]}" "$BASE/api/bookings?per_page=5"; grep -c '"total":1' /tmp/body)" 2001
check "confirm booking" "$(code "${S[@]}" "${J[@]}" -X PATCH -d '{"status":"confirmed"}' $BASE/api/bookings/$BID/status)" 200
check "invalid status -> 422" "$(code "${S[@]}" "${J[@]}" -X PATCH -d '{"status":"done"}' $BASE/api/bookings/$BID/status)" 422
check "cancel booking" "$(code "${S[@]}" "${J[@]}" -X PATCH -d '{"status":"cancelled"}' $BASE/api/bookings/$BID/status)" 200
check "cancelled is final -> 409" "$(code "${S[@]}" "${J[@]}" -X PATCH -d '{"status":"paid"}' $BASE/api/bookings/$BID/status)" 409

echo "Ledger"
code "${S[@]}" "${J[@]}" -d "$(booking 3 $EXP TUR-10)" $BASE/api/bookings >/dev/null
check "staff cannot read ledger -> 403" "$(code "${S[@]}" $BASE/api/ledger)" 403
check "admin reads ledger" "$(code "${A[@]}" $BASE/api/ledger)" 200
code "${A[@]}" $BASE/api/ledger/summary >/dev/null
check "summary excludes cancelled booking" "$(grep -c 'JPN-07' /tmp/body)" 0
check "summary revenue 3 x 21,750,000" "$(grep -c '"revenue":65250000' /tmp/body)" 1

echo "HTTP"
check "unknown endpoint -> 404" "$(code "${A[@]}" $BASE/api/nope)" 404
check "wrong method -> 405" "$(code "${A[@]}" -X DELETE $BASE/api/bookings)" 405
check "CORS allowed origin echoed" "$(curl -s -o /dev/null -D - -H 'Origin: http://allowed.test' $BASE/api/products | grep -ci 'access-control-allow-origin: http://allowed.test')" 1
check "CORS other origin not allowed" "$(curl -s -o /dev/null -D - -H 'Origin: http://evil.test' $BASE/api/products | grep -ci 'access-control-allow-origin')" 0
check "demo page served" "$(curl -s $BASE/ | grep -c '<title>Travel Booking')" 1
check "no PHP errors in server log" "$(grep -ci 'fatal\|warning\|deprecated' /tmp/travel_server.log)" 0

echo "Rate limit"
kill $SERVER; sleep 0.5
RATE_LIMIT_MAX=3 php -S 127.0.0.1:$PORT -t public public/index.php >/dev/null 2>&1 & SERVER=$!
sleep 1
for i in 1 2 3; do code "${A[@]}" $BASE/api/products/SGP-03 >/dev/null; done
check "4th request in a minute -> 429" "$(code "${A[@]}" $BASE/api/products/SGP-03)" 429

echo; echo "passed: $PASS  failed: $FAIL"; [ $FAIL -eq 0 ]
