#!/bin/bash
echo "=== Testing Laravel Application ==="

echo -e "\n1. Checking container status:"
docker compose ps

echo -e "\n2. Testing PHP:"
docker compose exec backend php -v

echo -e "\n3. Testing Laravel:"
docker compose exec backend php artisan --version

echo -e "\n4. Testing .env file:"
docker compose exec backend ls -la .env

echo -e "\n5. Testing storage permissions:"
docker compose exec backend ls -la storage/

echo -e "\n6. Testing database connection:"
docker compose exec backend php artisan db:show 2>&1 | head -20

echo -e "\n7. Checking routes:"
docker compose exec backend php artisan route:list 2>&1 | head -20

echo -e "\n8. Testing HTTP request:"
curl -I http://localhost:8000 2>&1 | head -10

echo -e "\n9. Checking logs:"
docker compose exec backend tail -20 storage/logs/laravel.log 2>&1

echo -e "\n=== Diagnostic Complete ==="