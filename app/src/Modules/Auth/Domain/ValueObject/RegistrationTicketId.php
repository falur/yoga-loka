<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

final readonly class RegistrationTicketId extends AbstractUuidV7Id {}
