<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Registry;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Application\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Dto\NotificationTypeDefinitionCollection;
use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * In-memory реестр определений видов. Stateful (накапливает регистрации модулей-источников за
 * время жизни приложения), поэтому биндится синглтоном в NotificationsBootloader.
 */
final class NotificationTypeRegistry implements NotificationTypeRegistryContract
{
    /**
     * @var array<string, NotificationTypeDefinition>
     */
    private array $definitions = [];

    #[\Override]
    public function register(NotificationTypeDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            $code = $definition->code()->value();

            if (isset($this->definitions[$code])) {
                throw NotificationTypeRegistryException::duplicateType($definition->code());
            }

            $this->definitions[$code] = $definition;
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
