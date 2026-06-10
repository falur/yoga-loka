#!/usr/bin/env bash
set -euo pipefail

# Подготовка тестовых PostgreSQL-баз перед запуском тестов.
#
# Валидирует TEST_PARALLEL_PROCESSES ДО любых destructive-операций и очищает
# только точные тестовые базы: базовую yoga_loka_test и активные worker-базы
# yoga_loka_test_1 ... yoga_loka_test_${TEST_PARALLEL_PROCESSES}. Очистку MinIO
# bucket-ов выполняет docker/minio/ensure-buckets.sh (mc недоступен в этом
# образе), поэтому здесь только базы.
#
# Допустимые значения TEST_PARALLEL_PROCESSES: пусто (только базовый режим) или
# целое от 1 до 4. Любое другое значение завершает скрипт до очистки данных.

PROCESSES="${TEST_PARALLEL_PROCESSES:-}"

if [[ -n "${PROCESSES}" && ! "${PROCESSES}" =~ ^[1-4]$ ]]; then
    echo "[prepare] Ошибка: TEST_PARALLEL_PROCESSES должен быть от 1 до 4, получено: ${PROCESSES}" >&2
    exit 1
fi

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-postgres}"
export PGPASSWORD="${DB_PASSWORD:-password}"

test_databases=("yoga_loka_test")

if [[ -n "${PROCESSES}" ]]; then
    for (( worker=1; worker<=PROCESSES; worker++ )); do
        test_databases+=("yoga_loka_test_${worker}")
    done
fi

echo "[prepare] Подготовка тестовых баз: ${test_databases[*]}"

for test_database in "${test_databases[@]}"; do
    # Защита: чистим только точные тестовые имена баз.
    case "${test_database}" in
        yoga_loka_test|yoga_loka_test_[1-4]) ;;
        *)
            echo "[prepare] Ошибка: запрещено очищать не тестовую базу ${test_database}" >&2
            exit 1
            ;;
    esac

    echo "[prepare] Очищаю schema public в базе: ${test_database}"
    psql \
        --host="${DB_HOST}" \
        --port="${DB_PORT}" \
        --username="${DB_USERNAME}" \
        --dbname="${test_database}" \
        --variable=ON_ERROR_STOP=1 \
        --command='DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;'
done

echo "[prepare] Тестовые базы очищены: ${test_databases[*]}"
