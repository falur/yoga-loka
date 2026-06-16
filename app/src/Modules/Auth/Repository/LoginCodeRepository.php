<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<LoginCode>
 */
final class LoginCodeRepository extends Repository
{
    /**
     * Последний непогашенный код по email с блокировкой строки. Срок не фильтруется в запросе:
     * различение Expired/NoCode делает доменный сценарий по isExpired(). forUpdate применяется
     * прямо в цепочке select() (через Repository::forUpdate() флаг блокировки теряется при clone).
     */
    public function findActiveByEmailForUpdate(EmailAddress $email): LoginCode|null
    {
        return $this->select()
            ->where('email', $email->value())
            ->where('consumed_at', null)
            ->orderBy(['id' => 'DESC'])
            ->forUpdate()
            ->fetchOne();
    }

    /**
     * Последний непогашенный код по email без блокировки — для троттлинга «не чаще одного кода
     * в минуту»: сценарий смотрит на createdAt последнего активного кода.
     */
    public function findActiveByEmail(EmailAddress $email): LoginCode|null
    {
        return $this->select()
            ->where('email', $email->value())
            ->where('consumed_at', null)
            ->orderBy(['id' => 'DESC'])
            ->fetchOne();
    }
}
