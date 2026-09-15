<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Registry;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Application\Dto\NotificationTypeDefinitionCollection;
use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;

/**
 * In-memory реестр определений видов. Stateful (накапливает регистрации модулей-источников за
 * время жизни приложения), поэтому биндится синглтоном в NotificationsBootloader. Код вида приходит
 * из публичного определения строкой и превращается здесь в доменный NotificationTypeCode: формат
 * `module.action` проверяется один раз, на регистрации.
 */
final class NotificationTypeRegistry implements NotificationTypeCatalogContract
{
    /**
     * @var array<string, NotificationTypeDefinition>
     */
    private array $definitions = [];

    #[\Override]
    public function register(NotificationTypeDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            $code = NotificationTypeCode::fromString($definition->code());

            if (isset($this->definitions[$code->value()])) {
                throw NotificationTypeRegistryException::duplicateType($code);
            }

            $this->definitions[$code->value()] = $definition;
        }
    }

    #[\Override]
    public function all(): NotificationTypeDefinitionCollection
    {
        return new NotificationTypeDefinitionCollection(\array_values($this->definitions));
    }

    #[\Override]
    public function get(NotificationTypeCode $code): NotificationTypeDefinition
    {
        return $this->definitions[$code->value()]
            ?? throw NotificationTypeRegistryException::unknownType($code);
    }
}
