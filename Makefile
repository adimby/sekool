# FANABE development commands
# Usage: make up | make vps | make migrate | make seed | make test | make lint

COMPOSE ?= docker compose
COMPOSE_DEV := $(COMPOSE) -f compose.yaml -f compose.dev.yaml
API := cd api

.PHONY: up down vps vps-down migrate seed test test-isolation lint fresh analyse demo

demo:
	@echo "Local (API + Vite sur la machine) :"
	@echo "  1. make up"
	@echo "  2. cd api && composer install && cp -n .env.example .env && php artisan key:generate && php artisan migrate --seed && php artisan serve"
	@echo "  3. cd web && npm install && npm run dev"
	@echo "  4. http://127.0.0.1:5173"
	@echo ""
	@echo "VPS (tout dans Docker, un seul port HTTP) :"
	@echo "  cp -n .env.example .env   # renseigner APP_URL=http://VOTRE_IP"
	@echo "  make vps"
	@echo "  ouvrir http://VOTRE_IP (ou :FANABE_HTTP_PORT)"

up:
	$(COMPOSE_DEV) up -d

down:
	$(COMPOSE) --profile vps -f compose.yaml -f compose.dev.yaml down

vps:
	$(COMPOSE) --profile vps up -d --build
	@echo ""
	@echo "FANABE démarre sur le port $${FANABE_HTTP_PORT:-80}."
	@echo "Renseignez APP_URL dans .env (IP ou domaine), puis : docker compose --profile vps up -d"

vps-down:
	$(COMPOSE) --profile vps down

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
