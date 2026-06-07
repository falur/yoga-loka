<?php

declare(strict_types=1);

if ($argc !== 3) {
    \fwrite(STDERR, "Использование: php docker/test/assert-coverage.php <clover.xml> <minimum>\n");
    exit(2);
}

$coverageFile = $argv[1];
$minimumCoverage = (float) $argv[2];

if (!\is_file($coverageFile)) {
    \fwrite(STDERR, \sprintf("Файл покрытия `%s` не найден.\n", $coverageFile));
    exit(2);
}

$coverage = \simplexml_load_file($coverageFile);

if (!$coverage instanceof SimpleXMLElement) {
    \fwrite(STDERR, \sprintf("Файл покрытия `%s` не удалось прочитать.\n", $coverageFile));
    exit(2);
}

$metrics = $coverage->xpath('/coverage/project/metrics')[0] ?? null;

if (!$metrics instanceof SimpleXMLElement) {
    \fwrite(STDERR, "В Clover-отчёте нет общих метрик покрытия.\n");
    exit(2);
}

$coveredStatements = (int) $metrics['coveredstatements'];
$statements = (int) $metrics['statements'];
$coveragePercent = $statements === 0 ? 100.0 : ($coveredStatements / $statements) * 100;

if ($coveragePercent < $minimumCoverage) {
    \fwrite(
        STDERR,
        \sprintf(
            "Покрытие %.2f%% ниже обязательного порога %.2f%%.\n",
            $coveragePercent,
            $minimumCoverage,
        ),
    );
    exit(1);
}

\printf("Покрытие %.2f%% соответствует порогу %.2f%%.\n", $coveragePercent, $minimumCoverage);
