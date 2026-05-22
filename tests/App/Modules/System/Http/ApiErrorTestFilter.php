<?php

declare(strict_types=1);

namespace Tests\App\Modules\System\Http;

use Spiral\Filters\Attribute\CastingErrorMessage;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;

final class ApiErrorTestFilter extends AttributesFilter
{
    #[Post]
    #[CastingErrorMessage(message: 'Возраст должен быть числом')]
    public int $age;
}
