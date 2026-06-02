Backend setup (dev):
1. cp .env.example .env
2. docker compose up -d
3. enter container: docker exec -it <app> bash
4. composer install
5. php artisan key:generate
6. php artisan migrate --seed
7. php artisan serve --host=0.0.0.0 --port=9000
