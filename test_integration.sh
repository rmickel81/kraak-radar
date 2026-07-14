#!/bin/bash
# Test de integración de Kraak Radar

BASE="http://localhost:8080"
echo "=== 1. Login ==="
SESSION=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -X POST "$BASE/index.php" \
  -d "action=login&email=test@kraak.app&password=test123" \
  -o /dev/null -w "%{http_code}" -D /tmp/headers.txt)
echo "Login: $SESSION"

echo "=== 2. Dashboard ==="
DASH=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  "$BASE/dashboard.php" -o /dev/null -w "%{http_code}")
echo "Dashboard: $DASH"

echo "=== 3. Prompts ==="
PROMPTS=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  "$BASE/prompts.php" -o /dev/null -w "%{http_code}")
echo "Prompts: $PROMPTS"

echo "=== 4. Competidores ==="
COMPS=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  "$BASE/competitors.php" -o /dev/null -w "%{http_code}")
echo "Competidores: $COMPS"

echo "=== 5. Fuentes ==="
SRC=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  "$BASE/sources.php" -o /dev/null -w "%{http_code}")
echo "Fuentes: $SRC"

echo "=== 6. Export ==="
EXP=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  "$BASE/export.php" -o /dev/null -w "%{http_code}")
echo "Export: $EXP"

echo "=== 7. Logout ==="
LOGOUT=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt \
  "$BASE/logout.php" -o /dev/null -w "%{http_code}")
echo "Logout: $LOGOUT"

echo "=== Resultado ==="
echo "Todo OK" && rm -f /tmp/cookies.txt /tmp/headers.txt
