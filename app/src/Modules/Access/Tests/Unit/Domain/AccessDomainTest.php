<?php

declare(strict_types=1);

namespace App\Modules\Access\Tests\Unit\Domain;

use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\Entity\RolePermission;
use App\Modules\Access\Domain\Entity\UserRole;
use App\Modules\Access\Domain\Enum\PermissionName;
use App\Modules\Access\Domain\Enum\RoleName;
use App\Modules\Access\Domain\ValueObject\PermissionId;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Modules\Access\Domain\ValueObject\RoleId;
use App\Modules\Access\Domain\ValueObject\RolePermissionId;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Modules\Access\Domain\ValueObject\UserRoleId;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AccessDomainTest extends TestCase
{
    public function testRoleSlugValidatesAndSerializes(): void
    {
        $slug = RoleSlug::fromString('admin');

        self::assertSame('admin', $slug->value());
        self::assertTrue($slug->equals(RoleSlug::fromString('admin')));
        self::assertSame('admin', (string) $slug);
        self::assertSame('admin', $slug->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        RoleSlug::fromString('Admin');
    }

    public function testPermissionSlugValidatesAndSerializes(): void
    {
        $slug = PermissionSlug::fromString('user.ban');

        self::assertSame('user.ban', $slug->value());
        self::assertTrue($slug->equals(PermissionSlug::fromString('user.ban')));
        self::assertSame('user.ban', (string) $slug);
        self::assertSame('user.ban', $slug->jsonSerialize());

        $this->expectException(InvalidDomainValueException::class);

        PermissionSlug::fromString('.bad');
    }

    public function testEnumsReturnCanonicalSlugs(): void
    {
        self::assertSame('admin', RoleName::Admin->slug()->value());
        self::assertSame('user.ban', PermissionName::UserBan->slug()->value());
        self::assertSame('user.verify', PermissionName::UserVerify->slug()->value());
        self::assertSame('user.role.assign', PermissionName::UserRoleAssign->slug()->value());
    }

    public function testEntitiesCreateWithUuidV7Ids(): void
    {
        $userId = UserId::generate();
        $role = Role::create(RoleSlug::fromString('admin'));
        $permission = Permission::create(PermissionSlug::fromString('user.ban'));
        $rolePermission = RolePermission::create(roleId: $role->id, permissionId: $permission->id);
        $userRole = UserRole::create(userId: $userId, roleId: $role->id);

        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString($role->id->value())->getVersion());
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString($permission->id->value())->getVersion());
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString($rolePermission->id->value())->getVersion());
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString($userRole->id->value())->getVersion());
        self::assertSame('admin', $role->slug->value());
        self::assertSame('user.ban', $permission->slug->value());
        self::assertTrue($rolePermission->roleId->equals($role->id));
        self::assertTrue($rolePermission->permissionId->equals($permission->id));
        self::assertTrue($userRole->userId->equals($userId));
        self::assertTrue($userRole->roleId->equals($role->id));

        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString(RoleId::generate()->value())->getVersion());
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString(PermissionId::generate()->value())->getVersion());
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString(RolePermissionId::generate()->value())->getVersion());
        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString(UserRoleId::generate()->value())->getVersion());
    }
}
