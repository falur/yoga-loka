<?php

declare(strict_types=1);

$autoloadFiles = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

foreach ($autoloadFiles as $autoloadFile) {
    if (\is_file($autoloadFile)) {
        require_once $autoloadFile;

        return;
    }
}

throw new \RuntimeException('Composer autoload file was not found.');
