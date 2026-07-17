#!/bin/bash
# Test de integración de Kraak Radar
# Requiere el stack docker corriendo: docker compose up -d
# Uso: ./test_integration.sh   (o BASE=http://localhost:8081 ./test_integration.sh)

BASE="${BASE:-http://localhost:8081}"
JAR=$(mktemp /tmp/kr_cookies.XXXXXX)
PASS=0; FAIL=0

check() { # check <nombre> <esperado> <actual>
    if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  OK   $1 ($3)";
    else FAIL=$((FAIL+1)); echo "  FAIL $1 — esperado $2, obtenido $3"; fi
}

get_csrf() { # extrae el token CSRF de una página
    curl -s -c "$JAR" -b "$JAR" "$1" | grep -oP 'name="_csrf" value="\K[^"]+' | head -1
}

echo "=== 1. Páginas públicas ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/index.php")
check "GET index.php" "200" "$CODE"

echo "=== 2. CSRF obligatorio en login ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/index.php" \
    -d "action=login&email=x@x.com&password=whatever123")
check "POST login sin CSRF → 403" "403" "$CODE"

echo "=== 3. Login con CSRF ==="
CSRF=$(get_csrf "$BASE/index.php")
[ -n "$CSRF" ] && { PASS=$((PASS+1)); echo "  OK   token CSRF obtenido"; } || { FAIL=$((FAIL+1)); echo "  FAIL token CSRF no encontrado"; }

CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$JAR" -b "$JAR" -X POST "$BASE/index.php" \
    --data-urlencode "_csrf=$CSRF" \
    --data-urlencode "action=login" \
    --data-urlencode "email=test@kraak.app" \
    --data-urlencode "password=test12345")
check "POST login correcto → 302" "302" "$CODE"

echo "=== 4. Páginas autenticadas ==="
for page in dashboard.php prompts.php competitors.php sources.php costs.php export.php settings.php; do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$JAR" -b "$JAR" "$BASE/$page")
    check "GET $page" "200" "$CODE"
done

echo "=== 5. Redirect a login sin sesión ==="
rm -f "$JAR"; JAR=$(mktemp /tmp/kr_cookies.XXXXXX)
CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/dashboard.php")
check "GET dashboard sin sesión → 302" "302" "$CODE"

echo "=== 6. API de registro (landing) ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/api/register.php" \
    -H "Content-Type: application/json" \
    -H "Origin: https://rmickel81.github.io" \
    -d "{\"name\":\"Test User\",\"email\":\"test-$RANDOM@example.com\",\"plan\":\"Pro\",\"domain\":\"example.com\"}")
check "POST api/register → 201" "201" "$CODE"

CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/api/register.php" \
    -H "Content-Type: application/json" \
    -d '{"name":"X","email":"bad","plan":"Pro","domain":"example.com"}')
check "POST api/register email inválido → 400" "400" "$CODE"

echo ""
echo "=== Resultado: $PASS OK, $FAIL FAIL ==="
rm -f "$JAR"
[ "$FAIL" = "0" ]
