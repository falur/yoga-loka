<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan\Fixtures;

/**
 * Автозагружаемый интерфейс-маркер для теста правила (роль app-маркера SetBasedWrite).
 * PHPStan распознаёт implementsInterface() только для интерфейса, доступного рефлексии,
 * поэтому маркер должен быть реальным автозагружаемым типом, а не stub.
 */
interface SetBasedWriteTestMarker {}
