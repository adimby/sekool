# FANABE development commands
# Usage: make up | make migrate | make seed | make test | make lint

COMPOSE ?= docker compose
API := cd api

.PHONY: up down migrate seed test test-isolation lint fresh analyse

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
