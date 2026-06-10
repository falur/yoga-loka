#!/usr/bin/env bash
set -euo pipefail

# Прогрев Cycle schema cache в тестовых runtime-каталогах.
# Без параллельного запуска прогревается только базовый режим (runtime/testing).
# При TEST_PARALLEL_PROCESSES=N дополнительно прогреваются worker-ы 1..N
# (runtime/testing-1 ... runtime/testing-N), чтобы они не компилировали schema
# параллельно и первый тест не платил цену прогрева.

PROCESSES="${TEST_PARALLEL_PROCESSES:-0}"

echo "[warmup] Старт прогрева Cycle schema cache"
echo "[warmup] Worker-процессов для прогрева: ${PROCESSES}"

warm_runtime() {
    local token="$1"
    local label runtime_dir

    if [[ -z "${token}" ]]; then
        label="базовый режим"
        runtime_dir="runtime/testing"
    else
        label="worker ${token}"
        runtime_dir="runtime/testing-${token}"
    fi

    echo "[warmup] Прогрев: ${label}, runtime=${runtime_dir}"
    # Чистим оба кэша, чтобы прогрев пересобрал их свежими: Cycle schema и
    # tokenizer targets (иначе после изменения Entity/класса тесты прочитали бы
    # устаревший кэш, ведь TOKENIZER_CACHE_TARGETS включён в tests/bootstrap.php).
    rm -f "${runtime_dir}/cache/cycle.php"
    rm -rf "${runtime_dir}/cache/listeners"
    TEST_TOKEN="${token}" php tests/warmup.php
}

# Базовый режим прогреваем всегда.
warm_runtime ""

# Активные worker-runtime прогреваем только при заданном параллельном запуске.
if [[ "${PROCESSES}" =~ ^[1-9][0-9]*$ ]]; then
    for (( worker=1; worker<=PROCESSES; worker++ )); do
        warm_runtime "${worker}"
    done
fi

echo "[warmup] Прогрев Cycle schema cache завершён"
