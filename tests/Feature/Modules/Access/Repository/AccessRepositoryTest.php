<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Access\Repository;

use App\Modules\Access\Domain\Collection\PermissionCollection;
use App\Modules\Access\Domain\Collection\RoleCollection;
use App\Modules\Access\Domain\Collection\RolePermissionCollection;
use App\Modules\Access\Domain\Collection\UserRoleCollection;
use App\Modules\Access\Domain\Entity\Permission;
use App\Modules\Access\Domain\Entity\Role;
use App\Modules\Access\Domain\Entity\RolePermission;
use App\Modules\Access\Domain\Entity\UserRole;
use App\Modules\Access\Domain\ValueObject\PermissionSlug;
use App\Modules\Access\Domain\ValueObject\RoleSlug;
use App\Modules\Access\Repository\PermissionRepository;
use App\Modules\Access\Repository\RolePermissionRepository;
use App\Modules\Access\Repository\RoleRepository;
use App\Modules\Access\Repository\UserRoleRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class AccessRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndFindsRolesAndPermissions(): void
    {
        $role = Role::create(RoleSlug::fromString('admin'));
        $permission = Permission::create(PermissionSlug::fromString('user.ban'));

        $this->entityManager()->persist($role);
        $this->entityManager()->persist($permission);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        self::assertInstanceOf(Role::class, $this->roleRepository()->findById($role->id));
        self::assertInstanceOf(Role::class, $this->roleRepository()->findBySlug(RoleSlug::fromString('admin')));
        self::assertInstanceOf(Permission::class, $this->permissionRepository()->findById($permission->id));
        self::assertInstanceOf(
            Permission::class,
            $this->permissionRepository()->findBySlug(PermissionSlug::fromString('user.ban')),
        );

        $rolesByIds = $this->roleRepository()->findByIds($role->id);
        $permissionsByIds = $this->permissionRepository()->findByIds($permission->id);

        self::assertInstanceOf(RoleCollection::class, $rolesByIds);
        self::assertCount(1, $rolesByIds);
        self::assertInstanceOf(PermissionCollection::class, $permissionsByIds);
        self::assertCount(1, $permissionsByIds);
        self::assertInstanceOf(RoleCollection::class, $this->roleRepository()->findAll());
        self::assertCount(1, $this->roleRepository()->findAll());
        self::assertInstanceOf(PermissionCollection::class, $this->permissionRepository()->findAll());
        self::assertCount(1, $this->permissionRepository()->findAll());
    }

    public function testStoresAndFindsLinks(): void
    {
        $user = $this->createUser();
        $role = Role::create(RoleSlug::fromString('admin'));
        $permission = Permission::create(PermissionSlug::fromString('user.ban'));
        $rolePermission = RolePermission::create(roleId: $role->id, permissionId: $permission->id);
        $userRole = UserRole::create(userId: $user->id, roleId: $role->id);

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($role);
        $this->entityManager()->persist($permission);
        $this->entityManager()->persist($rolePermission);
        $this->entityManager()->persist($userRole);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $rolePermissions = $this->rolePermissionRepository()->findByRoleId($role->id);
        $userRoles = $this->userRoleRepository()->findByUserId($user->id);

        self::assertInstanceOf(RolePermissionCollection::class, $rolePermissions);
        self::assertCount(1, $rolePermissions);
        self::assertTrue($this->rolePermissionRepository()->exists(roleId: $role->id, permissionId: $permission->id));
        self::assertInstanceOf(UserRoleCollection::class, $userRoles);
        self::assertCount(1, $userRoles);
        self::assertTrue($this->userRoleRepository()->exists(userId: $user->id, roleId: $role->id));
    }

    public function testDuplicateRoleSlugFails(): void
    {
        $this->entityManager()->persist(Role::create(RoleSlug::fromString('admin')));
        $this->entityManager()->persist(Role::create(RoleSlug::fromString('admin')));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicatePermissionSlugFails(): void
    {
        $this->entityManager()->persist(Permission::create(PermissionSlug::fromString('user.ban')));
        $this->entityManager()->persist(Permission::create(PermissionSlug::fromString('user.ban')));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateRolePermissionFails(): void
    {
        $role = Role::create(RoleSlug::fromString('admin'));
        $permission = Permission::create(PermissionSlug::fromString('user.ban'));

        $this->entityManager()->persist($role);
        $this->entityManager()->persist($permission);
        $this->entityManager()->persist(RolePermission::create(roleId: $role->id, permissionId: $permission->id));
        $this->entityManager()->persist(RolePermission::create(roleId: $role->id, permissionId: $permission->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testDuplicateUserRoleFails(): void
    {
        $user = $this->createUser();
        $role = Role::create(RoleSlug::fromString('admin'));

        $this->entityManager()->persist($user);
        $this->entityManager()->persist($role);
        $this->entityManager()->persist(UserRole::create(userId: $user->id, roleId: $role->id));
        $this->entityManager()->persist(UserRole::create(userId: $user->id, roleId: $role->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    private function createUser(): User
    {
        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString('access@example.com'),
            nickname: UserNickname::fromString('access.user'),
            locale: Locale::Ru,
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function roleRepository(): RoleRepository
    {
        return $this->getContainer()->get(RoleRepository::class);
    }

    private function permissionRepository(): PermissionRepository
    {
        return $this->getContainer()->get(PermissionRepository::class);
    }

    private function rolePermissionRepository(): RolePermissionRepository
    {
        return $this->getContainer()->get(RolePermissionRepository::class);
    }

    private function userRoleRepository(): UserRoleRepository
    {
        return $this->getContainer()->get(UserRoleRepository::class);
    }
}
