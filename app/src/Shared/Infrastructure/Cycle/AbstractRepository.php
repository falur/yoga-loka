<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

use Cycle\ORM\ORM;
use Cycle\ORM\Select;
use Cycle\ORM\Select\Repository;

/**
 * Базовый репозиторий, чей select() возвращает WhenSelect с методом when().
 *
 * Cycle жёстко создаёт в RepositoryProvider плоский Select, поэтому WhenSelect строим сами
 * из orm+role и кладём базовым select-ом репозитория. Тогда select(), repo-forUpdate() и
 * __clone() работают на WhenSelect.
 *
 * @template TEntity of object
 *
 * @extends Repository<TEntity>
 *
 * @method WhenSelect<TEntity> select()
 */
abstract class AbstractRepository extends Repository
{
    /**
     * @param Select<TEntity> $select
     */
    public function __construct(Select $select, ORM $orm, string $role)
    {
        parent::__construct($select);

        /** @var WhenSelect<TEntity> $whenSelect */
        $whenSelect = new WhenSelect(orm: $orm, role: $role);
        // Scope источника — как в RepositoryProvider (scoped-сущностей сейчас нет → no-op,
        // строка ради совместимости на будущее).
        $whenSelect->scope($orm->getSource($role)->getScope());

        // Метка @readonly на родительском $select — это PHPDoc, а не настоящий readonly PHP:
        // в рантайме такая перезапись разрешена (проверено на PHP 8.5). Подменяем плоский
        // Select на WhenSelect, чтобы базовым запросом репозитория стал именно он.
        // @phpstan-ignore property.readOnlyByPhpDocAssignOutOfClass
        $this->select = $whenSelect;
    }
}
