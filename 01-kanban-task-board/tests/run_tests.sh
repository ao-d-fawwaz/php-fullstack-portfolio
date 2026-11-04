#!/usr/bin/env bash
# End-to-end API test against PHP's built-in server and a throwaway SQLite database.
# Usage: bash tests/run_tests.sh
set -u
cd "$(dirname "$0")/.."
DB=$(mktemp -d)/test.sqlite
export DB_DSN="sqlite:$DB" DB_USER="" DB_PASS=""
php tests/sql.php "$DB" --schema database/schema.sqlite.sql
php database/create_user.php alice secret123 >/dev/null
php database/create_user.php bob secret456 >/dev/null
PORT=8765; BASE="http://127.0.0.1:$PORT"
php -S 127.0.0.1:$PORT -t public >/tmp/kanban_server.log 2>&1 & SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 1
PASS=0; FAIL=0
check() { if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); echo "  FAIL $1 (expected $3, got $2)"; fi; }
login() { # $1 user $2 pass $3 cookiejar -> prints csrf token
  local jar=$3
  local t=$(curl -s -c "$jar" -b "$jar" "$BASE/login.php" | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -c "$jar" -b "$jar" -d "csrf_token=$t&username=$1&password=$2" "$BASE/login.php"
  curl -s -c "$jar" -b "$jar" "$BASE/index.php" | grep -o 'const CSRF_TOKEN = "[^"]*"' | sed 's/.*= "//;s/"//'
}
A=$(mktemp); B=$(mktemp)
code() { curl -s -o /tmp/body -w '%{http_code}' "$@"; }

echo "Auth"
check "API without login -> 401" "$(code $BASE/api/task.php)" 401
check "board redirects to login" "$(curl -s -o /dev/null -w '%{redirect_url}' $BASE/index.php | grep -c login.php)" 1
TA=$(login alice secret123 $A); TB=$(login bob secret456 $B)
check "wrong password rejected" "$(curl -s -d 'username=alice&password=x' $BASE/login.php | grep -c 'Session expired\|Wrong')" 1
check "login gives CSRF token" "$([ ${#TA} -eq 64 ] && echo yes)" yes

echo "Tasks"
H=(-b $A -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json')
check "create without CSRF -> 403" "$(code -b $A -X POST -d '{"title":"x","column":"sales"}' $BASE/api/task.php)" 403
check "create task" "$(code "${H[@]}" -X POST -d '{"task_uuid":"task_abc12345","title":"Call client <b>","column":"sales"}' $BASE/api/task.php)" 201
check "unknown column -> 422" "$(code "${H[@]}" -X POST -d '{"title":"x","column":"hr"}' $BASE/api/task.php)" 422
check "empty title -> 422" "$(code "${H[@]}" -X POST -d '{"title":"  ","column":"sales"}' $BASE/api/task.php)" 422
code -b $A $BASE/api/task.php >/dev/null
check "list returns task with raw title" "$(grep -c 'Call client <b>' /tmp/body)" 1
check "move to finance" "$(code "${H[@]}" -X PUT -d '{"column":"finance"}' "$BASE/api/task.php?task_uuid=task_abc12345")" 200
code -b $A $BASE/api/task.php >/dev/null
check "task now in finance" "$(grep -c '"column_name":"finance"' /tmp/body)" 1
check "history recorded move" "$(php tests/sql.php $DB "select count(*) from task_history where action='moved'")" 1
check "bob cannot see alice's task" "$(code -b $B $BASE/api/task.php; grep -c task_abc12345 /tmp/body)" 2000
check "bob cannot update alice's task" "$(code -b $B -H "X-CSRF-Token: $TB" -H 'Content-Type: application/json' -X PUT -d '{"title":"hacked"}' "$BASE/api/task.php?task_uuid=task_abc12345")" 404

echo "Chat"
check "send message" "$(code -b $A -H "X-CSRF-Token: $TA" -d 'task_id=task_abc12345&message=Hello team' $BASE/api/send_message.php)" 201
check "read messages" "$(code -b $A "$BASE/api/get_messages.php?task_id=task_abc12345"; grep -c 'Hello team' /tmp/body)" 2001
check "bob cannot read chat" "$(code -b $B "$BASE/api/get_messages.php?task_id=task_abc12345")" 404

echo "Attachments"
printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > /tmp/ok.pdf
printf '<?php echo "pwned"; ?>' > /tmp/evil.php
cp /tmp/evil.php /tmp/evil.png
check "upload pdf" "$(code -b $A -H "X-CSRF-Token: $TA" -F task_id=task_abc12345 -F 'files[]=@/tmp/ok.pdf;type=application/pdf' $BASE/api/upload_attachment.php)" 201
check "upload .php rejected" "$(code -b $A -H "X-CSRF-Token: $TA" -F task_id=task_abc12345 -F 'files[]=@/tmp/evil.php' $BASE/api/upload_attachment.php)" 422
check "php disguised as .png rejected" "$(code -b $A -H "X-CSRF-Token: $TA" -F task_id=task_abc12345 -F 'files[]=@/tmp/evil.png;type=image/png' $BASE/api/upload_attachment.php)" 422
check "no .php file stored" "$(ls public/uploads | grep -c '\.php$')" 0
code -b $A "$BASE/api/get_attachments.php?task_id=task_abc12345&recent=5" >/dev/null
ID=$(grep -o '"id":[0-9]*' /tmp/body | head -1 | cut -d: -f2)
check "list attachments" "$(grep -c 'ok.pdf' /tmp/body)" 1
check "owner can download" "$(code -b $A "$BASE/api/download.php?id=$ID")" 200
check "bob cannot download" "$(code -b $B "$BASE/api/download.php?id=$ID")" 404
check "delete attachment" "$(code -b $A -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' -d "{\"attachment_id\":$ID}" $BASE/api/delete_attachment.php)" 200
check "file removed from disk" "$(ls public/uploads | grep -vc -e '^\.' -e gitkeep)" 0

echo "Delete task"
check "delete task" "$(code "${H[@]}" -X DELETE "$BASE/api/task.php?task_uuid=task_abc12345")" 200
check "messages cascade-deleted" "$(php tests/sql.php $DB 'select count(*) from task_messages')" 0
check "board page renders" "$(curl -s -b $A $BASE/index.php | grep -c 'Signed in as <strong>alice')" 1
check "no PHP errors in server log" "$(grep -ci 'fatal\|warning\|error' /tmp/kanban_server.log)" 0

echo; echo "passed: $PASS  failed: $FAIL"; [ $FAIL -eq 0 ]
