<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Model;

final readonly class PropertyMetadata
{
    public const string SOURCE_QUERY = 'query';
    public const string SOURCE_PATH = 'path';
    public const string SOURCE_BODY = 'body';
    public const string SOURCE_DATA = 'data';
    public const string SOURCE_NONE = 'none';
    public function __construct(public string $name, public string $type, public bool $nullable, public bool $hasDefault, public string $source, public ?string $listItemType = null)
    {
    }
    public function isRequired(): bool
    {
        return !$this->nullable && !$this->hasDefault;
    }
}
