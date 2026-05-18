COMPOSE_FILE ?= docker/docker-compose.dev.yml
ENV_FILE ?= .env
PROJECT_NAME ?= yoga-loka-spiral-2
APP_SERVICE ?= app-http
CMD ?= bash
COMPOSE = docker compose -f $(COMPOSE_FILE) --env-file $(ENV_FILE) -p $(PROJECT_NAME)

.PHONY: up down restart composer-install test phpstan shell logs migrate reset-test

up:
	@echo "[make] Старт цели up: project=$(PROJECT_NAME)"
	@$(COMPOSE) up -d --build
	@echo "[make] Цель up завершена"

down:
	@echo "[make] Старт цели down: project=$(PROJECT_NAME)"
	@$(COMPOSE) down
	@echo "[make] Цель down завершена"

restart: down up

composer-install:
	@echo "[make] Старт цели composer-install: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) composer install
	@echo "[make] Цель composer-install завершена"

test: reset-test
	@echo "[make] Старт цели test: profile=test"
	@$(COMPOSE) --profile test run --rm test-runner
	@echo "[make] Цель test завершена"

phpstan:
	@echo "[make] Старт цели phpstan: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) composer phpstan
	@echo "[make] Цель phpstan завершена"

shell:
	@echo "[make] Старт цели shell: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) $(CMD)

logs:
	@echo "[make] Старт цели logs"
	@$(COMPOSE) logs --tail=200
	@echo "[make] Цель logs завершена"

migrate:
	@echo "[make] Старт цели migrate: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) php app.php migrate --force
	@echo "[make] Цель migrate завершена"

reset-test:
	@echo "[make] Старт цели reset-test"
	@test "$${DB_TEST_DATABASE:-yoga_loka_test}" = "yoga_loka_test"
	@test "$${MINIO_TEST_BUCKET:-yoga-loka-test}" = "yoga-loka-test"
	@$(COMPOSE) up -d postgres redis minio mailpit
	@$(COMPOSE) run --rm postgres-init
	@$(COMPOSE) exec -T postgres psql -U "$${DB_USERNAME:-postgres}" -d yoga_loka_test -v ON_ERROR_STOP=1 -c 'DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;'
	@$(COMPOSE) run --rm minio-init reset-test
	@echo "[make] Цель reset-test завершена"
