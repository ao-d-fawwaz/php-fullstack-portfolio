#!/usr/bin/env bash
# Unit test for the bracket engine + end-to-end API test (PHP built-in server, throwaway SQLite DB).
# Usage: bash tests/run_tests.sh
set -u
cd "$(dirname "$0")/.."
echo "Bracket engine"; php tests/BracketEngineTest.php || exit 1
DB=$(mktemp -d)/test.sqlite
export DB_DSN="sqlite:$DB" DB_USER="" DB_PASS="" TOURNAMENT_DATE="2026-12-12"
php tests/sql.php "$DB" --schema database/schema.sqlite.sql
php tests/sql.php "$DB" --schema database/seed.sql
php database/create_user.php panitia secret123 >/dev/null
PORT=8768; BASE="http://127.0.0.1:$PORT"
php -S 127.0.0.1:$PORT -t public >/tmp/bracket_server.log 2>&1 & SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 1
PASS=0; FAIL=0
check() { if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected $3, got $2)"; fi; }
code() { curl -s -o /tmp/body -w '%{http_code}' "$@"; }
sql() { php tests/sql.php $DB "$1"; }
JAR=$(mktemp)
T=$(curl -s -c $JAR -b $JAR $BASE/login.php | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -c $JAR -b $JAR -d "csrf_token=$T&username=panitia&password=secret123" $BASE/login.php
CSRF=$(curl -s -b $JAR $BASE/admin.php | grep -o 'const CSRF = "[^"]*"' | sed 's/.*= "//;s/"//')
H=(-b $JAR -H "X-CSRF-Token: $CSRF" -H 'Content-Type: application/json')
K=kumite-M-adults-light

echo "Public access"
check "public participant list" "$(code $BASE/api/participants.php)" 200
check "public list hides birth dates" "$(grep -c birth_date /tmp/body)" 0
check "categories" "$(code $BASE/api/bracket.php; grep -o '"key"' /tmp/body | wc -l)" 2002
check "public cannot register" "$(code -H 'Content-Type: application/json' -X POST -d '{}' $BASE/api/participants.php)" 401
check "public cannot generate" "$(code -H 'Content-Type: application/json' -d "{\"action\":\"generate\",\"category\":\"$K\"}" $BASE/api/bracket.php)" 401

echo "Registration"
check "register 8th kumite fighter" "$(code "${H[@]}" -X POST -d '{"name":"Joko Susilo","sex":"M","birth_date":"1994-08-17","weight_kg":67,"belt":"Black","dojo":"Kelapa Gading","event":"kumite"}' $BASE/api/participants.php)" 201
check "auto category label" "$(grep -c 'Light (<70 kg)' /tmp/body)" 1
check "invalid participant -> 422" "$(code "${H[@]}" -X POST -d '{"name":"","sex":"X","birth_date":"2025-01-01","weight_kg":5,"belt":"Pink","dojo":"","event":"judo"}' $BASE/api/participants.php)" 422
check "7 field errors" "$(grep -o '"name"\|"sex"\|"birth_date"\|"weight_kg"\|"belt"\|"dojo"\|"event"' /tmp/body | wc -l)" 7
check "missing CSRF -> 403" "$(code -b $JAR -H 'Content-Type: application/json' -X POST -d '{}' $BASE/api/participants.php)" 403
check "organiser sees birth dates" "$(curl -s -b $JAR $BASE/api/participants.php | grep -c birth_date)" 1

echo "Bracket"
check "unknown category -> 404" "$(code "${H[@]}" -d '{"action":"generate","category":"unknown-key"}' $BASE/api/bracket.php)" 404
check "generate 8-fighter bracket" "$(code "${H[@]}" -d "{\"action\":\"generate\",\"category\":\"$K\"}" $BASE/api/bracket.php)" 201
check "7 matches (4+2+1)" "$(sql "select count(*) from matches")" 7
check "no same-dojo first-round match" "$(sql "select count(*) from matches m join participants r on r.id=m.red_id join participants w on w.id=m.white_id where m.round=1 and r.dojo=w.dojo")" 0
M1=$(sql "select id from matches where round=1 and position=0"); R1=$(sql "select red_id from matches where round=1 and position=0")
W1=$(sql "select white_id from matches where round=1 and position=0")
check "record winner" "$(code "${H[@]}" -d "{\"action\":\"winner\",\"match_id\":$M1,\"winner_id\":$R1}" $BASE/api/bracket.php)" 200
check "winner advanced to semi-final red slot" "$(sql "select red_id from matches where round=2 and position=0")" "$R1"
check "outsider cannot win -> 422" "$(code "${H[@]}" -d "{\"action\":\"winner\",\"match_id\":$M1,\"winner_id\":99}" $BASE/api/bracket.php)" 422
check "semi-final not ready -> 409" "$(code "${H[@]}" -d "{\"action\":\"winner\",\"match_id\":$(sql "select id from matches where round=2 and position=0"),\"winner_id\":$R1}" $BASE/api/bracket.php)" 409
check "correct a result (change winner)" "$(code "${H[@]}" -d "{\"action\":\"winner\",\"match_id\":$M1,\"winner_id\":$W1}" $BASE/api/bracket.php)" 200
check "semi-final slot updated" "$(sql "select red_id from matches where round=2 and position=0")" "$W1"
# play out the rest: red always wins
for r in 1 2 3; do
  for id in $(php -r "\$p=new PDO('sqlite:$DB'); foreach(\$p->query('select id from matches where round=$r and winner_id is null') as \$m) echo \$m['id'],' ';"); do
    RED=$(sql "select red_id from matches where id=$id")
    code "${H[@]}" -d "{\"action\":\"winner\",\"match_id\":$id,\"winner_id\":$RED}" $BASE/api/bracket.php >/dev/null
  done
done
code $BASE/api/bracket.php?category=$K >/dev/null
check "champion decided" "$(grep -c '"champion":"' /tmp/body)" 1
check "earlier result locked after next round" "$(code "${H[@]}" -d "{\"action\":\"winner\",\"match_id\":$M1,\"winner_id\":$R1}" $BASE/api/bracket.php)" 409
check "regenerate with results needs force" "$(code "${H[@]}" -d "{\"action\":\"generate\",\"category\":\"$K\"}" $BASE/api/bracket.php)" 409
check "participant in bracket cannot be removed" "$(code "${H[@]}" -X DELETE "$BASE/api/participants.php?id=$R1")" 409

echo "Byes"
check "3-fighter kata bracket" "$(code "${H[@]}" -d '{"action":"generate","category":"kata-F-adults"}' $BASE/api/bracket.php)" 201
check "bye auto-advanced to final" "$(sql "select count(*) from matches m join brackets b on b.id=m.bracket_id where b.category_key='kata-F-adults' and m.round=2 and (m.red_id is not null or m.white_id is not null)")" 1
check "bye match marked won" "$(sql "select count(*) from matches m join brackets b on b.id=m.bracket_id where b.category_key='kata-F-adults' and m.round=1 and m.white_id is null and m.winner_id=m.red_id")" 1

echo "Pages"
check "viewer page renders" "$(curl -s $BASE/index.php | grep -c 'Organiser login')" 1
check "admin page needs login" "$(curl -s -o /dev/null -w '%{redirect_url}' $BASE/admin.php | grep -c login.php)" 1
check "no PHP errors in server log" "$(grep -ci 'fatal\|warning\|deprecated' /tmp/bracket_server.log)" 0
echo; echo "passed: $PASS  failed: $FAIL"; [ $FAIL -eq 0 ]
