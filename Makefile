# FANABE development commands
# Usage: make up | make migrate | make seed | make test | make lint

COMPOSE ?= docker compose
API := cd api

.PHONY: up down migrate seed test test-isolation lint fresh analyse demo

demo:
	@echo "1. PostgreSQL + Redis : make up   (si pas déjà lancés localement)"
	@echo "2. API :  cd api && composer install && cp -n .env.example .env && php artisan key:generate && php artisan migrate --seed && php artisan serve"
	@echo "3. Web :  cd web && npm install && npm run dev"
	@echo "4. Ouvrir http://127.0.0.1:5173"

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

migrate:
	$(API) && php artisan migrate

seed:
	$(API) && php artisan db:seed

fresh:
	$(API) && php artisan migrate:fresh --seed

test:
	$(API) && php artisan test

test-isolation:
	$(API) && php artisan test --testsuite=Isolation

lint:
	$(API) && vendor/bin/pint --test

analyse:
	$(API) && vendor/bin/phpstan analyse --memory-limit=1G
