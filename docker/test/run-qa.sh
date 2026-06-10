#!/usr/bin/env bash
set -euo pipefail

# Полный quality gate: стиль, PHPStan и один coverage-run (PCOV) без отдельного
# обычного прогона тестов перед покрытием. composer qa = cs + phpstan +
# test-coverage, поэтому набор тестов запускается ровно один раз с покрытием.

echo "[test-runner] Этап 1/4: очистка runtime-артефактов"
bash docker/test/clean-run-artifacts.sh

echo "[test-runner] Этап 2/4: миграции тестовых баз"
bash docker/test/migrate-test-databases.sh

echo "[test-runner] Этап 3/4: прогрев Cycle schema cache"
bash docker/test/warmup.sh

echo "[test-runner] Этап 4/4: стиль, PHPStan и один coverage-run (PCOV)"
COMPOSER_PROCESS_TIMEOUT=900 composer qa

echo "[test-runner] Composer QA завершён успешно"
