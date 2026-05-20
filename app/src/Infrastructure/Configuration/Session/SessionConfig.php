<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Session;

use App\Infrastructure\Configuration\TypedConfig;
use Spiral\Core\Container\Autowire;
use Spiral\Session\Handler\CacheHandler;

final readonly class SessionConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'session';
    }

    /**
     * @param Autowire<CacheHandler>|null $handler
     */
    public function __construct(
        public int $lifetime,
        public string $cookie,
        public bool $secure,
        public ?string $sameSite,
        public ?Autowire $handler,
    ) {}
}
