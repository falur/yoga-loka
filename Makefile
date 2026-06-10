COMPOSE_FILE ?= docker/docker-compose.dev.yml
ENV_FILE ?= .env
PROJECT_NAME ?= yoga-loka-spiral-2
APP_SERVICE ?= app-http
CMD ?= bash
COMPOSE = docker compose -f $(COMPOSE_FILE) --env-file $(ENV_FILE) -p $(PROJECT_NAME)

.PHONY: up down restart composer-install test test-unit test-kernel test-feature test-coverage warmup phpstan qa qa-build shell logs migrate reset-test

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

test: TEST_PARALLEL_PROCESSES = 4
test: reset-test
	@echo "[make] Старт цели test: полный gate, ParaTest процессов=$(TEST_PARALLEL_PROCESSES), один reset и один набор тестов"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner
	@echo "[make] Цель test завершена"

test-unit:
	@echo "[make] Старт цели test-unit: suite=Unit, режим=lightweight, без reset и внешних сервисов"
	@$(COMPOSE) --profile test run --rm --no-deps test-runner bash -lc 'bash docker/test/assert-unit-suite-is-light.sh && vendor/bin/phpunit --testsuite Unit'
	@echo "[make] Цель test-unit завершена"

test-kernel: reset-test
	@echo "[make] Старт цели test-kernel: suite=Kernel, причина=нужен Spiral kernel и container, запуск после reset-test"
	@$(COMPOSE) --profile test run --rm --no-deps test-runner bash -lc 'bash docker/test/migrate-test-databases.sh && bash docker/test/warmup.sh && vendor/bin/phpunit --testsuite Kernel'
	@echo "[make] Цель test-kernel завершена"

test-feature: TEST_PARALLEL_PROCESSES = 4
test-feature: reset-test
	@echo "[make] Старт цели test-feature: suite=Feature, ParaTest процессов=$(TEST_PARALLEL_PROCESSES), запуск после reset-test"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash -lc 'bash docker/test/migrate-test-databases.sh && bash docker/test/warmup.sh && vendor/bin/paratest --processes "$${TEST_PARALLEL_PROCESSES:-4}" --testsuite Feature'
	@echo "[make] Цель test-feature завершена"

test-coverage: TEST_PARALLEL_PROCESSES = 4
test-coverage: reset-test
	@echo "[make] Старт цели test-coverage: suite=Unit,Kernel,Feature, драйвер=PCOV, ParaTest процессов=$(TEST_PARALLEL_PROCESSES)"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash -lc 'bash docker/test/clean-run-artifacts.sh && bash docker/test/migrate-test-databases.sh && bash docker/test/warmup.sh && COMPOSER_PROCESS_TIMEOUT=900 composer test-coverage'
	@echo "[make] Цель test-coverage завершена"

warmup:
	@echo "[make] Старт цели warmup: прогрев Cycle schema cache в тестовых runtime-каталогах"
	@$(COMPOSE) --profile test run --rm --no-deps test-runner bash docker/test/warmup.sh
	@echo "[make] Цель warmup завершена"

phpstan:
	@echo "[make] Старт цели phpstan: service=$(APP_SERVICE)"
	@$(COMPOSE) run --rm $(APP_SERVICE) composer phpstan
	@echo "[make] Цель phpstan завершена"

qa: TEST_PARALLEL_PROCESSES = 4
qa: reset-test
	@echo "[make] Старт цели qa: стиль, PHPStan и один coverage-run (PCOV), процессов=$(TEST_PARALLEL_PROCESSES), без пересборки образа"
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash docker/test/run-qa.sh
	@echo "[make] Цель qa завершена"

qa-build: TEST_PARALLEL_PROCESSES = 4
qa-build: reset-test
	@echo "[make] Старт цели qa-build: пересборка образа + тот же QA, процессов=$(TEST_PARALLEL_PROCESSES)"
	@$(COMPOSE) --profile test run --rm --build -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash docker/test/run-qa.sh
	@echo "[make] Цель qa-build завершена"

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
	@echo "[make] Старт цели reset-test: процессов=$(TEST_PARALLEL_PROCESSES)"
	@test "$${DB_TEST_DATABASE:-yoga_loka_test}" = "yoga_loka_test"
	@test "$${MINIO_TEST_BUCKET:-yoga-loka-test}" = "yoga-loka-test"
	@$(COMPOSE) up -d postgres redis minio mailpit
	@$(COMPOSE) run --rm postgres-init
	@$(COMPOSE) --profile test run --rm --no-deps -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) test-runner bash docker/test/prepare-parallel-resources.sh
	@$(COMPOSE) run --rm -e TEST_PARALLEL_PROCESSES=$(TEST_PARALLEL_PROCESSES) minio-init reset-test
	@echo "[make] Цель reset-test завершена"
