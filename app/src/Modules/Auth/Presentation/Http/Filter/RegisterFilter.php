<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Header;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\Input\RemoteAddress;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $ticket;

    // Фильтр проверяет сырой ввод, а UserName нормализует значение (trim + схлопывание
    // пробелов) до проверки длины. Поэтому фильтр намеренно строже VO на краевом вводе:
    // ведущие/замыкающие пробелы у имени из ~100 значимых символов могут дать 422 там, где VO
    // после trim принял бы. Это допустимый понятный 422 на ненормализованный ввод, а не 500
    // из домена — выравнивать нормализацию в фильтре не нужно.
    #[Post]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: "/^[\\p{L}\\p{M}' -]+$/u")]
    public string $name;

    // Никнейм нормализуется в UserNickname (trim + lower), поэтому формат проверяем
    // регистронезависимо; длина 3–30, запрет двух точек подряд — как в VO. Фильтр намеренно
    // строже VO: пробелы по краям VO срезал бы, а фильтр на них даёт 422 — это понятный 422 на
    // ненормализованный ввод, выравнивать нормализацию в фильтре не нужно.
    #[Post]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$/i')]
    #[Assert\Regex(pattern: '/\.\./', match: false)]
    public string $nickname;

    // Захватываются из соединения и заголовка автоматически (клиент ничего не присылает) для
    // метаданных сессии. SOURCE_NONE — в OpenAPI-контракт тела запроса не попадают.
    #[RemoteAddress]
    public string|null $clientIp = null;

    #[Header(key: 'User-Agent')]
    public string|null $userAgent = null;
}
