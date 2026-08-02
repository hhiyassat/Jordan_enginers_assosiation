#!/usr/bin/env bash
# =============================================================================
# ESP-v2 · check-local-dev.sh
# =============================================================================
# Non-destructive readiness check for a new developer machine.
#
#   • Verifies docker-compose services are running.
#   • Verifies the Laravel backend responds on the browser-facing port.
#   • Verifies the Vite dev-server proxy target matches the backend.
#   • Verifies /api/v1/auth/me returns HTTP 200 {"user":null} both direct
#     and through Vite.
#   • Warns when frontend/backend CAPTCHA flags disagree.
#   • Warns when the demo seed hasn't been applied yet.
#
# What this script NEVER does:
#   • no `docker compose down -v` (that would drop your database volume)
#   • no `git clean`, no `git reset`
#   • no writes to any DB
#   • no printing of secrets, API tokens, or session cookies
#
# Exit codes:
#   0 — all checks PASS
#   1 — any check FAIL (script still finishes so you see every issue)
#   2 — dependency missing (curl / jq / docker)
#
# Usage:
#   ./scripts/check-local-dev.sh
#   BACKEND_URL=http://localhost:9000 FRONTEND_URL=http://localhost:5174 \
#     ./scripts/check-local-dev.sh
# =============================================================================
set -u

BACKEND_URL="${BACKEND_URL:-http://localhost:8080}"
FRONTEND_URL="${FRONTEND_URL:-http://localhost:5173}"
DEMO_EMAIL="ahmed@demo.esp"

fail_count=0
warn_count=0
pass_count=0

pass() { printf "\033[32m[PASS]\033[0m %s\n" "$1"; pass_count=$((pass_count + 1)); }
warn() { printf "\033[33m[WARN]\033[0m %s\n" "$1"; warn_count=$((warn_count + 1)); }
fail() { printf "\033[31m[FAIL]\033[0m %s\n" "$1"; fail_count=$((fail_count + 1)); }

need() {
    if ! command -v "$1" >/dev/null 2>&1; then
        printf "\033[31m[MISSING]\033[0m %s not installed — install it and re-run.\n" "$1"
        exit 2
    fi
}

echo "==> ESP-v2 local-dev readiness check"
echo "    BACKEND_URL=$BACKEND_URL"
echo "    FRONTEND_URL=$FRONTEND_URL"
echo

need curl
need docker

# --- 1. Docker services ------------------------------------------------------
if docker compose ps --format '{{.Service}}\t{{.State}}' 2>/dev/null | grep -qE "app\s+running"; then
    pass "docker-compose 'app' service is running"
else
    fail "docker-compose 'app' service is not running — start it with: docker compose up -d"
fi

for svc in postgres redis; do
    if docker compose ps --format '{{.Service}}\t{{.State}}' 2>/dev/null | grep -qE "${svc}\s+running"; then
        pass "docker-compose '${svc}' service is running"
    else
        warn "docker-compose '${svc}' service is not running"
    fi
done

# --- 2. Backend health -------------------------------------------------------
backend_status=$(curl -s -o /dev/null -w '%{http_code}' "${BACKEND_URL}/api/v1/auth/me" || echo "0")
if [ "$backend_status" = "200" ]; then
    pass "backend /api/v1/auth/me → HTTP 200"
else
    fail "backend /api/v1/auth/me → HTTP ${backend_status} (expected 200) — check that docker-compose 'app' is reachable on ${BACKEND_URL}"
fi

backend_body=$(curl -s "${BACKEND_URL}/api/v1/auth/me" || echo "")
if echo "$backend_body" | grep -q '"user":null'; then
    pass "backend unauthenticated /me returns {\"user\":null}"
else
    fail "backend unauthenticated /me did not return {\"user\":null} — got: ${backend_body:0:120}"
fi

# --- 3. Vite dev server + proxy ---------------------------------------------
vite_status=$(curl -s -o /dev/null -w '%{http_code}' "${FRONTEND_URL}/api/v1/auth/me" || echo "0")
if [ "$vite_status" = "200" ]; then
    pass "vite proxy /api/v1/auth/me → HTTP 200 (proxy target matches backend)"
elif [ "$vite_status" = "0" ]; then
    warn "vite dev server not reachable at ${FRONTEND_URL} — start it with: cd frontend && npm run dev"
else
    fail "vite proxy /api/v1/auth/me → HTTP ${vite_status} — likely wrong VITE_DEV_PROXY_TARGET in frontend/.env"
fi

# --- 4. CAPTCHA config alignment --------------------------------------------
fe_captcha="$(grep -E '^VITE_CAPTCHA_ENABLED=' /Users/husseinhiyassat/tenders/esp-v2/frontend/.env 2>/dev/null | tail -1 | cut -d= -f2 | tr -d '\r' || echo)"
fe_captcha="${fe_captcha:-$(grep -E '^VITE_CAPTCHA_ENABLED=' /Users/husseinhiyassat/tenders/esp-v2/frontend/.env.example 2>/dev/null | tail -1 | cut -d= -f2 | tr -d '\r' || echo "false")}"

be_captcha=$(docker compose exec -T app printenv CAPTCHA_ENABLED 2>/dev/null | tr -d '\r' || echo "")

if [ -n "$be_captcha" ] && [ -n "$fe_captcha" ]; then
    if [ "$fe_captcha" = "$be_captcha" ]; then
        pass "CAPTCHA config aligned (frontend=${fe_captcha} backend=${be_captcha})"
    else
        fail "CAPTCHA config DISAGREES (frontend=${fe_captcha} backend=${be_captcha}) — copy docker-compose.override.yml.example to docker-compose.override.yml OR set VITE_CAPTCHA_ENABLED accordingly"
    fi
else
    warn "cannot compare CAPTCHA config (frontend=${fe_captcha:-?} backend=${be_captcha:-?})"
fi

# --- 5. Demo account presence ------------------------------------------------
# Try a passwordless probe: post an intentionally wrong password. A 401
# means the user exists; a 500/404 suggests the seed hasn't been applied.
demo_probe_status=$(curl -s -o /dev/null -w '%{http_code}' \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -X POST "${BACKEND_URL}/api/v1/auth/login" \
    -d '{"email":"'"${DEMO_EMAIL}"'","password":"__probe_wrong__"}' 2>/dev/null || echo "0")
if [ "$demo_probe_status" = "401" ] || [ "$demo_probe_status" = "422" ]; then
    pass "demo user ${DEMO_EMAIL} responds (HTTP ${demo_probe_status} on wrong password)"
else
    warn "demo user probe returned HTTP ${demo_probe_status} — run: docker compose exec app php artisan db:seed --class=DemoSeeder"
fi

# --- 6. Migrations applied ---------------------------------------------------
if docker compose exec -T app php artisan migrate:status 2>/dev/null | grep -q "Pending"; then
    fail "pending migrations detected — run: docker compose exec app php artisan migrate"
else
    pass "no pending migrations (or migrate:status unavailable)"
fi

# --- 7. Worker + scheduler ---------------------------------------------------
if docker compose ps --format '{{.Service}}\t{{.State}}' 2>/dev/null | grep -qE "worker\s+running"; then
    pass "docker-compose 'worker' service is running"
else
    warn "docker-compose 'worker' service is not running (queue jobs won't process)"
fi
if docker compose ps --format '{{.Service}}\t{{.State}}' 2>/dev/null | grep -qE "scheduler\s+running"; then
    pass "docker-compose 'scheduler' service is running"
else
    warn "docker-compose 'scheduler' service is not running (cron won't run)"
fi

# --- Summary -----------------------------------------------------------------
echo
echo "==> Summary: ${pass_count} PASS · ${warn_count} WARN · ${fail_count} FAIL"

if [ "$fail_count" -gt 0 ]; then
    exit 1
fi
exit 0
