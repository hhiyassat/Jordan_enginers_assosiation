#!/bin/bash
# ESP v2 — One-command setup
# Run from: ~/tenders/esp-v2/
# Requirements: PHP 8.2+, Composer, Node 18+

set -e

echo ""
echo "╔════════════════════════════════════════════╗"
echo "║    ESP v2 — Eqratech Services Platform     ║"
echo "╚════════════════════════════════════════════╝"
echo ""

# ── Backend ──────────────────────────────────────────────────────────

echo "→ Setting up Laravel backend..."

if [ ! -d "backend/vendor" ]; then
    echo "  Running composer create-project into backend-base/..."
    composer create-project laravel/laravel backend-base --quiet

    echo "  Merging Laravel scaffold into backend/ (preserving custom files)..."
    # -n = no-overwrite: our custom files (app/, routes/, migrations/, etc.) win
    cp -rn backend-base/. backend/

    rm -rf backend-base
    echo "  Merge complete."
else
    echo "  backend/vendor/ already exists, skipping scaffold."
fi

cd backend

# Ensure required Laravel directories exist BEFORE any composer/artisan commands
mkdir -p bootstrap/cache
mkdir -p storage/framework/{cache,views,sessions}
mkdir -p storage/logs

# Copy base Laravel config files if missing (from framework vendor package)
if [ ! -f "config/app.php" ] && [ -d "vendor/laravel/framework/config" ]; then
    echo "  Restoring missing config/ files from framework defaults..."
    cp -n vendor/laravel/framework/config/*.php config/
fi

# Install Sanctum if not already present
if ! grep -q "laravel/sanctum" composer.json 2>/dev/null; then
    composer require laravel/sanctum --quiet
fi

# Remove default conflicting migrations (our custom ones replace them)
rm -f database/migrations/0001_01_01_000000_create_users_table.php
rm -f database/migrations/0001_01_01_000001_create_cache_table.php
rm -f database/migrations/0001_01_01_000002_create_jobs_table.php

# Configure .env
if [ ! -f ".env" ]; then
    cp .env.example .env
fi

# Generate app key if missing
if grep -q "^APP_KEY=$" .env; then
    php artisan key:generate
fi

# SQLite setup (macOS-compatible sed)
if grep -q "DB_CONNECTION=mysql" .env; then
    sed -i '' 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env 2>/dev/null || \
    sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
fi

if grep -q "^DB_DATABASE=" .env; then
    sed -i '' 's/^DB_DATABASE=.*/DB_DATABASE=/' .env 2>/dev/null || \
    sed -i 's/^DB_DATABASE=.*/DB_DATABASE=/' .env
fi

touch database/database.sqlite

# Clear any cached config
php artisan config:clear 2>/dev/null || true
php artisan cache:clear  2>/dev/null || true

# Register the DemoSeeder in DatabaseSeeder if not already there
if ! grep -q "DemoSeeder" database/seeders/DatabaseSeeder.php 2>/dev/null; then
    cat > database/seeders/DatabaseSeeder.php << 'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DemoSeeder::class]);
    }
}
PHP
fi

# Run migrations + seed
php artisan migrate:fresh --seed

echo ""
echo "✓ Backend ready!"
echo ""
echo "  Admin:     admin@demo.esp   / Demo1234!"
echo "  Staff:     staff@demo.esp   / Demo1234!"
echo "  Auditor:   auditor@demo.esp / Demo1234!"
echo "  Applicant: ahmed@demo.esp   / Demo1234!"
echo ""

cd ..

# ── Frontend ─────────────────────────────────────────────────────────

echo "→ Setting up React frontend..."

cd frontend

if [ ! -f "package.json" ]; then
    echo "  Creating Vite + React + TypeScript project..."
    npm create vite@latest . -- --template react-ts
fi

npm install --silent
npm install react-router-dom --silent

# Add Vite proxy so /api calls reach the Laravel backend on 8002
if [ -f "vite.config.ts" ] && ! grep -q "proxy" vite.config.ts; then
    cat > vite.config.ts << 'TS'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8002',
    },
  },
})
TS
fi

# Add tailwind if not present
if ! grep -q "tailwindcss" package.json 2>/dev/null; then
    npm install -D tailwindcss postcss autoprefixer --silent
    npx tailwindcss init -p 2>/dev/null || true
fi

echo "✓ Frontend ready!"

cd ..

echo ""
echo "╔════════════════════════════════════════════╗"
echo "║            Setup Complete!                 ║"
echo "╚════════════════════════════════════════════╝"
echo ""
echo "  Terminal 1: cd ~/tenders/esp-v2/backend  && php artisan serve --port=8002"
echo "  Terminal 2: cd ~/tenders/esp-v2/frontend && npm run dev"
echo ""
echo "  Open: http://localhost:5173"
echo ""
