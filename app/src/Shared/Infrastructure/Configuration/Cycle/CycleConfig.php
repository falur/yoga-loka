<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Cycle;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use Cycle\ORM\Options;

final readonly class CycleConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'cycle';
    }

    /**
     * @param array<int|string, CycleCustomRelationConfig> $customRelations
     */
    public function __construct(
        public CycleSchemaConfig $schema,
        public bool $warmup,
        public Options|null $options,
        public array $customRelations,
    ) {}
}
