<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Ссылка записи на метку чужого модуля: своё VO модуля Posts поверх UUID v7,
 * без импорта TagId из домена Tags (Domain зависит только от своего домена и Shared).
 * Всегда задана, поэтому non-null id-VO без null-object фабрики (образец PostMediaReference).
 */
final readonly class PostTagReference extends AbstractUuidV7Id {}
