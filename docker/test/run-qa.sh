#!/usr/bin/env bash
set -euo pipefail

echo "[test-runner] Старт миграций тестовой базы"
php app.php migrate --force

echo "[test-runner] Старт проверки стиля"
composer cs

echo "[test-runner] Старт PHPStan"
composer phpstan

echo "[test-runner] Старт тестов"
composer test

echo "[test-runner] Старт проверки покрытия"
COMPOSER_PROCESS_TIMEOUT=900 XDEBUG_MODE=coverage composer test-coverage

echo "[test-runner] Composer QA завершён успешно"
