# Deployment — CSP + cookie config

Reference for the ops team. The frontend meta CSP and the backend
`SecurityHeaders` middleware carry the "braces" defense-in-depth
copy. The authoritative CSP for the SPA is the HTTP
`Content-Security-Policy` header set by the reverse proxy (nginx).

---

## JORD-83 — `frame-ancestors` must be an HTTP header

Browsers deliberately ignore the `frame-ancestors` directive when it
arrives inside a `<meta http-equiv="Content-Security-Policy">` tag
(CSP Level 2 §2.4). It has to be delivered as an HTTP response
header. The API's `SecurityHeaders` middleware already sets
`frame-ancestors 'none'` on every `/api/*` response, but the SPA
routes (`/`, `/login`, `/dashboard`, …) are served from nginx and
need their own header.

## JORD-82 — allow Google Fonts

The Cairo font family is loaded from `fonts.googleapis.com` in
`frontend/src/index.css`. The stylesheet and the font files need to
be whitelisted or the browser blocks them and falls back to `Segoe
UI` / `Arial`, which noticeably degrades the Arabic UI.

If you want to eliminate the external dependency entirely, self-host
Cairo under `frontend/public/fonts/` and remove the `@import` line
from `index.css`. Both `style-src` and `font-src` in the CSP can
then drop the Google Fonts entries.

## Recommended nginx site block

```nginx
server {
  listen 443 ssl http2;
  server_name  esp.example.jo;

  # ─── Serve the SPA ───────────────────────────────────────────
  root /var/www/esp-v2/frontend/dist;
  index index.html;

  # ─── Security headers ─────────────────────────────────────────
  # frame-ancestors is delivered here because the meta tag ignores it.
  add_header Content-Security-Policy "
    default-src 'self';
    script-src 'self';
    style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
    img-src   'self' data: blob:;
    font-src  'self' data: https://fonts.gstatic.com;
    connect-src 'self';
    frame-ancestors 'none';
    base-uri  'self';
    form-action 'self';
    object-src 'none';
  " always;

  add_header X-Frame-Options            "DENY"                                       always;
  add_header X-Content-Type-Options     "nosniff"                                    always;
  add_header Referrer-Policy            "strict-origin-when-cross-origin"            always;
  add_header Strict-Transport-Security  "max-age=31536000; includeSubDomains; preload" always;
  add_header Permissions-Policy         "camera=(), microphone=(), geolocation=(), payment=(), usb=()" always;

  # ─── SPA routing fallback ────────────────────────────────────
  location / { try_files $uri $uri/ /index.html; }

  # ─── Reverse-proxy API to Laravel ────────────────────────────
  location /api/ {
    proxy_pass         http://127.0.0.1:8000/api/;
    proxy_set_header   Host              $host;
    proxy_set_header   X-Real-IP         $remote_addr;
    proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header   X-Forwarded-Proto $scheme;
  }
}
```

---

## JORD-52 — session cookie env knobs

The httpOnly session cookie ("reload kicks the user to login") is
env-driven so ops can tune per deployment without redeploying
Laravel.

### `.env` variables

| var                             | default | notes                                                                                                                                       |
|---------------------------------|---------|---------------------------------------------------------------------------------------------------------------------------------------------|
| `ESP_SESSION_LIFETIME_MINUTES`  | `480`   | Cookie lifetime in minutes. Clamped to `[30, 43200]` (30 min .. 30 days) so a config typo can't emit a 100-year cookie or a 0-minute one. |
| `ESP_SESSION_COOKIE_SECURE`     | `auto`  | `auto` (production → true, else false), `true` (force on), `false` (force off — needed when the browser sees http:// behind a TLS LB).      |

Change and reload PHP-FPM / the queue worker; existing sessions
retain their original lifetime until they expire.

### Diagnosing "reload kicks me to login"

1. Open DevTools → Application → Cookies → the site's origin. Is
   `esp_session` present?
   - **No**: the login response never set it. Check the CORS +
     `credentials: 'include'` chain (both the frontend fetch and the
     backend CORS `supports_credentials` config in `config/cors.php`
     for cross-origin setups).
   - **Yes** but marked `Secure`: is the browser talking over http?
     Set `ESP_SESSION_COOKIE_SECURE=false`.
   - **Yes** and served, but the reload still bounces: check the
     cookie's Expires timestamp. If it's the past, the lifetime is
     too short — bump `ESP_SESSION_LIFETIME_MINUTES`.
2. Watch the network tab on reload:
   - `GET /api/v1/auth/me` should return `200 { user: null | <payload> }`
     (JORD-84 PM). A 401 here means the cookie isn't being sent —
     usually a `SameSite=Strict` cross-origin problem.

### Long-term hardening ideas

- Rotate the token on activity (sliding session) so an 8-hour cap
  doesn't kick users mid-workflow.
- Refresh cookie via a background call every N minutes to bump the
  Expires attribute without forcing re-login.

Neither is wired today. Ops workaround: bump `ESP_SESSION_LIFETIME_MINUTES`.
