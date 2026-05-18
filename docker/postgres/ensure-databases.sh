#!/usr/bin/env bash
set -euo pipefail

export PGPASSWORD="${POSTGRES_PASSWORD}"

echo "[postgres-init] Старт проверки баз PostgreSQL"

for database in ${POSTGRES_DATABASES}; do
    echo "[postgres-init] Проверяю базу: ${database}"

    exists="$(
        psql \
            --host="${POSTGRES_HOST}" \
            --port="${POSTGRES_PORT}" \
            --username="${POSTGRES_USER}" \
            --dbname=postgres \
            --tuples-only \
            --no-align \
            --command="SELECT 1 FROM pg_database WHERE datname = '${database}'"
    )"

    if [[ "${exists}" == "1" ]]; then
        echo "[postgres-init] База уже существует: ${database}"
        continue
    fi

    echo "[postgres-init] Создаю базу: ${database}"
    createdb \
        --host="${POSTGRES_HOST}" \
        --port="${POSTGRES_PORT}" \
        --username="${POSTGRES_USER}" \
        "${database}"
    echo "[postgres-init] База создана: ${database}"
done

echo "[postgres-init] Проверка баз PostgreSQL завершена"
