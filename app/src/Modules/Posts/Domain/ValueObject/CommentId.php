<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

final readonly class CommentId extends AbstractUuidV7Id {}
