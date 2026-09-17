#!/usr/bin/env bash
set -euo pipefail

# Проверка suite Unit: lightweight, без Spiral kernel и контейнера.
# Причина запуска: гарантировать, что быстрый `make test-unit` не поднимает
# kernel, container, БД, MinIO, Redis и прочие зависимости. Любой kernel-тест
# должен жить в suite Kernel (tests/Kernel или Tests/Integration модуля), а не
# в tests/Unit или Tests/Unit модуля.

# Глоб по каталогам модулей не перечисляет модули поимённо: новый модуль с
# Tests/Unit подключается к проверке без правки этого скрипта.
UNIT_DIRS=("tests/Unit")
for module_unit_dir in app/src/Modules/*/Tests/Unit; do
    if [[ -d "${module_unit_dir}" ]]; then
        UNIT_DIRS+=("${module_unit_dir}")
    fi
done

echo "[assert-unit] Старт проверки лёгкого suite: Unit (каталоги: ${UNIT_DIRS[*]})"

if [[ ! -d "tests/Unit" ]]; then
    echo "[assert-unit] Ошибка: каталог tests/Unit не найден" >&2
    exit 1
fi

# Запрещённые в Unit-каталогах паттерны. Регулярки ловят и alias-импорты вида
# `use Tests\TestCase as KernelTestCase`, потому что проверяется сам символ.
PATTERNS=(
    'Tests\\TestCase'
    'getContainer\('
    'Tests\\App\\TestKernel'
    'Spiral\\Testing\\TestCase'
)

violations=0

for unit_dir in "${UNIT_DIRS[@]}"; do
    for pattern in "${PATTERNS[@]}"; do
        matches="$(grep -rnE "${pattern}" "${unit_dir}" --include='*.php' || true)"

        if [[ -n "${matches}" ]]; then
            echo "[assert-unit] Запрещённый паттерн в suite Unit (${unit_dir}): ${pattern}" >&2
            echo "${matches}" >&2
            violations=$((violations + 1))
        fi
    done
done

if [[ "${violations}" -gt 0 ]]; then
    echo "[assert-unit] Suite Unit обязан быть лёгким: только PHPUnit\\Framework\\TestCase без kernel и container." >&2
    echo "[assert-unit] Перенесите kernel-тесты в suite Kernel (tests/Kernel или Tests/Integration модуля)." >&2
    exit 1
fi

echo "[assert-unit] Проверка пройдена: suite Unit лёгкий, kernel-зависимостей нет"
