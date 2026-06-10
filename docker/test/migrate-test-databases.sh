#!/usr/bin/env bash
set -euo pipefail

# Единственный владелец миграций тестового окружения.
#
# Выполняет миграции один раз для базовой тестовой базы yoga_loka_test и один
# раз для каждой активной worker-базы yoga_loka_test_1 ...
# yoga_loka_test_${TEST_PARALLEL_PROCESSES}. Вызывается после reset-test и
# очистки ресурсов, до warmup и тестов. reset-test и runner-скрипты не должны
# дублировать прямой вызов `php app.php migrate --force`.

PROCESSES="${TEST_PARALLEL_PROCESSES:-}"

# Кэш токенайзера и Cycle schema ускоряет boot app-kernel: первая миграция
# прогревает кэш, остальные читают его вместо повторного сканирования app/src.
# В .env эти флаги выключены для dev-автообновления, поэтому форсируем здесь.
export TOKENIZER_CACHE_TARGETS=true
export CYCLE_SCHEMA_CACHE=true

test_databases=("yoga_loka_test")

if [[ "${PROCESSES}" =~ ^[1-9][0-9]*$ ]]; then
    for (( worker=1; worker<=PROCESSES; worker++ )); do
        test_databases+=("yoga_loka_test_${worker}")
    done
fi

echo "[migrate] Миграции тестовых баз: ${test_databases[*]}"

for test_database in "${test_databases[@]}"; do
    echo "[migrate] Миграции базы: ${test_database}"
    DB_DATABASE="${test_database}" php app.php migrate --force
done

echo "[migrate] Миграции тестовых баз завершены: ${test_databases[*]}"
