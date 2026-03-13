#!/bin/bash
# PromptWalls Production Deployment Script
# Usage: ./deploy.sh

set -e

echo "=== PromptWalls Deploy ==="

# 1. Copy production env
if [ ! -f backend/.env ]; then
    cp .env.production backend/.env
    echo "[OK] .env created"
fi

# 2. Install PHP dependencies
docker compose -f docker-compose.prod.yml run --rm app composer install --no-dev --optimize-autoloader
echo "[OK] Composer dependencies installed"

# 3. Start all services
docker compose -f docker-compose.prod.yml up -d --build
echo "[OK] Services started"

# 4. Wait for MySQL to be ready
echo "Waiting for MySQL..."
sleep 15

# 5. Run migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
echo "[OK] Migrations done"

# 6. Generate app key if needed
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force
echo "[OK] App key set"

# 7. Optimize Laravel
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
echo "[OK] Laravel optimized"

# 8. Set permissions
docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
echo "[OK] Permissions set"

echo ""
echo "=== Deploy Complete ==="
echo "Access: http://$(hostname -I | awk '{print $1}')"
