#!/usr/bin/env bash
set -euo pipefail

mode="${1:-ensure}"

echo "[minio-init] Старт проверки bucket-ов MinIO"
mc alias set yoga-loka "${MINIO_ENDPOINT}" "${MINIO_ROOT_USER}" "${MINIO_ROOT_PASSWORD}" >/dev/null

if [[ "${mode}" == "reset-test" ]]; then
    if [[ "${MINIO_TEST_BUCKET:-yoga-loka-test}" != "yoga-loka-test" ]]; then
        echo "[minio-init] Ошибка: reset-test разрешён только для bucket yoga-loka-test" >&2
        exit 1
    fi

    echo "[minio-init] Очищаю тестовый bucket: yoga-loka-test"
    mc rm --recursive --force yoga-loka/yoga-loka-test >/dev/null || true
    mc mb --ignore-existing yoga-loka/yoga-loka-test >/dev/null
    echo "[minio-init] Тестовый bucket очищен: yoga-loka-test"
    exit 0
fi

for bucket in ${MINIO_BUCKETS}; do
    echo "[minio-init] Проверяю bucket: ${bucket}"

    if mc stat "yoga-loka/${bucket}" >/dev/null 2>&1; then
        echo "[minio-init] Bucket уже существует: ${bucket}"
        continue
    fi

    echo "[minio-init] Создаю bucket: ${bucket}"
    mc mb "yoga-loka/${bucket}" >/dev/null
    echo "[minio-init] Bucket создан: ${bucket}"
done

echo "[minio-init] Проверка bucket-ов MinIO завершена"
