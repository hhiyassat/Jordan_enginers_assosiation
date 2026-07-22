#!/usr/bin/env bash
# ESP v2 backend dev server — wraps `php artisan serve` with the
# elevated upload limits from php-dev.ini so drawing uploads (up to
# ~50MB) don't hit HTTP 413.

set -euo pipefail
cd "$(dirname "$0")"

PORT="${1:-8002}"

echo "▶ Starting Laravel dev server on port $PORT with elevated upload limits"
echo "   upload_max_filesize=60M · post_max_size=65M"
exec php -c php-dev.ini artisan serve --port="$PORT"
