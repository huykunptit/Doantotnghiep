DOCKER_COMPOSE=docker compose

.PHONY: up down restart build logs ps backend-shell frontend-shell migrate seed fresh

up:
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down

restart:
	$(DOCKER_COMPOSE) down
	$(DOCKER_COMPOSE) up -d

build:
	$(DOCKER_COMPOSE) build

logs:
	$(DOCKER_COMPOSE) logs -f --tail=200

ps:
	$(DOCKER_COMPOSE) ps

backend-shell:
	$(DOCKER_COMPOSE) exec backend sh

frontend-shell:
	$(DOCKER_COMPOSE) exec frontend sh

migrate:
	$(DOCKER_COMPOSE) exec backend php artisan migrate

seed:
	$(DOCKER_COMPOSE) exec backend php artisan db:seed

fresh:
	$(DOCKER_COMPOSE) exec backend php artisan migrate:fresh --seed
