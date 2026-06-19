<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Resource;

use App\Modules\Posts\Application\View\AuthorView;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class AuthorResource extends AbstractResource
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $avatarUrl,
    ) {}

    public static function fromView(AuthorView $author): self
    {
        return new self(
            userId: $author->userId,
            name: $author->name,
            avatarUrl: $author->avatarUrl,
        );
    }
}
