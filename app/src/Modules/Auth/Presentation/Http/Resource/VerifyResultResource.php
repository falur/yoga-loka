<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Resource;

use App\Modules\Auth\Application\Command\VerifyLoginCode\VerifyLoginCodeResult;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class VerifyResultResource extends AbstractResource
{
    public function __construct(
        public bool $needsProfile,
        public TokenPairResource|null $tokens,
        public string|null $registrationTicket,
    ) {}

    public static function fromResult(VerifyLoginCodeResult $verifyLoginCodeResult): self
    {
        return new self(
            needsProfile: $verifyLoginCodeResult->needsProfile,
            tokens: $verifyLoginCodeResult->tokens !== null
                ? TokenPairResource::fromPair($verifyLoginCodeResult->tokens)
                : null,
            registrationTicket: $verifyLoginCodeResult->registrationTicket,
        );
    }
}
