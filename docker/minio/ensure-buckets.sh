#!/usr/bin/env bash
set -euo pipefail

mode="${1:-ensure}"

echo "[minio-init] Старт проверки bucket-ов MinIO"
mc alias set yoga-loka "${MINIO_ENDPOINT}" "${MINIO_ROOT_USER}" "${MINIO_ROOT_PASSWORD}" >/dev/null

if [[ "${mode}" == "reset-test" ]]; then
    if [[ "${MINIO_TEST_BUCKET:-yoga-loka-test}" != "yoga-loka-test" ]]; then
        echo "[minio-init] Ошибка: reset-test разрешён только для базового bucket yoga-loka-test" >&2
        exit 1
    fi

    processes="${TEST_PARALLEL_PROCESSES:-0}"
    test_buckets=("yoga-loka-test")

    if [[ "${processes}" =~ ^[1-9][0-9]*$ ]]; then
        for (( worker=1; worker<=processes; worker++ )); do
            test_buckets+=("yoga-loka-test-${worker}")
        done
    fi

    echo "[minio-init] Очищаю тестовые bucket-ы: ${test_buckets[*]}"

    for test_bucket in "${test_buckets[@]}"; do
        # Защита: чистим только точные тестовые имена bucket-ов.
        case "${test_bucket}" in
            yoga-loka-test|yoga-loka-test-[1-4]) ;;
            *)
                echo "[minio-init] Ошибка: запрещено очищать не тестовый bucket ${test_bucket}" >&2
                exit 1
                ;;
        esac

        echo "[minio-init] Очищаю тестовый bucket: ${test_bucket}"
        mc rm --recursive --force "yoga-loka/${test_bucket}" >/dev/null || true
        mc mb --ignore-existing "yoga-loka/${test_bucket}" >/dev/null
    done

    echo "[minio-init] Тестовые bucket-ы очищены: ${test_buckets[*]}"
    exit 0
fi

for bucket in ${MINIO_BUCKETS}; do
    echo "[minio-init] Проверяю bucket: ${bucket}"

    if mc stat "yoga-loka/${bucket}" >/dev/null 2>&1; then
        echo "[minio-init] Bucket уже существует: ${bucket}"
    else
        echo "[minio-init] Создаю bucket: ${bucket}"
        mc mb "yoga-loka/${bucket}" >/dev/null
        echo "[minio-init] Bucket создан: ${bucket}"
    fi

    # media-public отдаёт прямые публичные URL, поэтому нужен anonymous read (download).
    # Идемпотентно применяем policy на каждом запуске.
    if [[ "${bucket}" == "media-public" ]]; then
        echo "[minio-init] Ставлю anonymous download policy: ${bucket}"
        mc anonymous set download "yoga-loka/${bucket}" >/dev/null
    fi
done

echo "[minio-init] Проверка bucket-ов MinIO завершена"
