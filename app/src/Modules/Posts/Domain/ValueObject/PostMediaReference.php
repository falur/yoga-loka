<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Ссылка записи на медиа чужого модуля: своё VO модуля Posts поверх UUID v7,
 * без импорта MediaId из домена Media (Domain зависит только от своего домена и Shared).
 * Всегда задан, поэтому non-null id-VO без null-object фабрики (образец MediaId).
 */
final readonly class PostMediaReference extends AbstractUuidV7Id {}
