#!/usr/bin/env bash
set -euo pipefail

# Очистка runtime-артефактов перед полным test/coverage-запуском, чтобы старые
# отчёты покрытия, runtime worker-ов, PHPUnit-кэш и тестовые OpenAPI-файлы не
# влияли на результат. Cycle schema cache пересоберёт warmup после очистки.

echo "[clean-artifacts] Очистка runtime-артефактов перед полным запуском"

rm -rf runtime/testing runtime/testing-1 runtime/testing-2 runtime/testing-3 runtime/testing-4
rm -rf runtime/testing-storage runtime/testing-storage-1 runtime/testing-storage-2 runtime/testing-storage-3 runtime/testing-storage-4
rm -rf runtime/.phpunit.cache
rm -f runtime/coverage/clover.xml
rm -f runtime/openapi-*-test.yml runtime/missing-openapi.yml

echo "[clean-artifacts] runtime-артефакты очищены"
