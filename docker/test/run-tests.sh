#!/usr/bin/env bash
set -euo pipefail

echo "[test-runner] Старт миграций тестовой базы"
php app.php migrate --force

echo "[test-runner] Старт PHPUnit и тестов PHPStan rules"
COMPOSER_PROCESS_TIMEOUT=900 composer test

echo "[test-runner] Тестовый запуск завершён успешно"
