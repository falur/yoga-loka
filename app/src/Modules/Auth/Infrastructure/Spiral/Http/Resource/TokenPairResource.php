<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Resource;

use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

final readonly class TokenPairResource extends AbstractResource
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $tokenType,
        public int $expiresIn,
    ) {}

    public static function fromPair(IssuedTokenPair $pair): self
    {
        return new self(
            accessToken: $pair->accessToken,
            refreshToken: $pair->refreshToken,
            tokenType: 'Bearer',
            expiresIn: $pair->expiresIn,
        );
    }
}
