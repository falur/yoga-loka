<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Exception;

use App\Modules\Media\Domain\Enum\MediaStorage;

final class MediaStorageNotConfiguredException extends \DomainException
{
    public static function bucketAliasMissing(MediaStorage $storage): self
    {
        return new self(\sprintf('Bucket-алиас %s не настроен в storage-конфиге.', $storage->value));
    }

    public static function bucketNameMissing(MediaStorage $storage): self
    {
        return new self(\sprintf('Для bucket-алиаса %s не задано имя бакета.', $storage->value));
    }

    public static function serverMissing(MediaStorage $storage, string $server): self
    {
        return new self(\sprintf('Сервер %s для bucket-алиаса %s не настроен.', $server, $storage->value));
    }
}
