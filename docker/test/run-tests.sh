#!/usr/bin/env bash
set -euo pipefail

# Полный тестовый gate: один reset (снаружи) и один набор тестов через ParaTest.
# Suite-ы Unit, Kernel и Feature запускаются один раз, без двойного прогона.

echo "[test-runner] Этап 1/5: проверка лёгкого unit suite"
bash docker/test/assert-unit-suite-is-light.sh

echo "[test-runner] Этап 2/5: очистка runtime-артефактов"
bash docker/test/clean-run-artifacts.sh

echo "[test-runner] Этап 3/5: миграции тестовых баз"
bash docker/test/migrate-test-databases.sh

echo "[test-runner] Этап 4/5: прогрев Cycle schema cache"
bash docker/test/warmup.sh

echo "[test-runner] Этап 5/5: тесты ParaTest, suite Unit,Kernel,Feature, процессов=${TEST_PARALLEL_PROCESSES:-4}"
vendor/bin/paratest --processes "${TEST_PARALLEL_PROCESSES:-4}" --testsuite Unit,Kernel,Feature

echo "[test-runner] Тестовый запуск завершён успешно"
