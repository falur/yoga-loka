#!/usr/bin/env bash
set -euo pipefail

# Проверка suite Unit: lightweight, без Spiral kernel и контейнера.
# Причина запуска: гарантировать, что быстрый `make test-unit` не поднимает
# kernel, container, БД, MinIO, Redis и прочие зависимости. Любой kernel-тест
# должен жить в suite Kernel (tests/Kernel), а не в tests/Unit.

UNIT_DIR="tests/Unit"

echo "[assert-unit] Старт проверки лёгкого suite: Unit (каталог ${UNIT_DIR})"

if [[ ! -d "${UNIT_DIR}" ]]; then
    echo "[assert-unit] Ошибка: каталог ${UNIT_DIR} не найден" >&2
    exit 1
fi

# Запрещённые в tests/Unit паттерны. Регулярки ловят и alias-импорты вида
# `use Tests\TestCase as KernelTestCase`, потому что проверяется сам символ.
PATTERNS=(
    'Tests\\TestCase'
    'getContainer\('
    'Tests\\App\\TestKernel'
    'Spiral\\Testing\\TestCase'
)

violations=0

for pattern in "${PATTERNS[@]}"; do
    matches="$(grep -rnE "${pattern}" "${UNIT_DIR}" --include='*.php' || true)"

    if [[ -n "${matches}" ]]; then
        echo "[assert-unit] Запрещённый паттерн в suite Unit: ${pattern}" >&2
        echo "${matches}" >&2
        violations=$((violations + 1))
    fi
done

if [[ "${violations}" -gt 0 ]]; then
    echo "[assert-unit] Suite Unit обязан быть лёгким: только PHPUnit\\Framework\\TestCase без kernel и container." >&2
    echo "[assert-unit] Перенесите kernel-тесты в suite Kernel (tests/Kernel)." >&2
    exit 1
fi

echo "[assert-unit] Проверка пройдена: suite Unit лёгкий, kernel-зависимостей нет"
