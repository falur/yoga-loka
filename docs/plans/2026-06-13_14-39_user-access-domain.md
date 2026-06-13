---
title: Слой данных/домена модулей User и Access (без Application и HTTP)
date: 2026-06-13 14:39
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-13_12-33_user-domain.md
---

# План реализации

## Задача

Реализовать **слой данных и домена** двух модулей по исследованию `docs/researches/2026-06-13_12-33_user-domain.md`:

- **`User`** — Entity `User`/`UserBan`/`ReservedNickname` с доменными методами, Value Object, enum, typecast, миграции, репозитории.
- **`Access`** — Entity `Role`/`Permission`/`RolePermission`/`UserRole`, VO, enum, typecast, миграции, репозитории.

Готово, когда: миграции создают все таблицы; Entity/VO/enum/typecast/репозитории работают и покрыты тестами (unit на домен + Feature на персистентность/репозитории); `make phpstan` и `make test-coverage` (100%) зелёные.

**Вне рамок этого плана** (подтверждено пользователем): весь слой **Application** (Command/Query DTO и Handler-ы, консольные команды, сидер RBAC, кросс-модульный поток аватара, проверки прав, оркестрация банов) и **HTTP-слой**. Это отдельный последующий план. Аутентификация (пароли, токены, verification-flow) тоже не проектируется. Доменные методы Entity (`ban()`, `unban()`, `confirmEmail()` и т.д.) создаются и юнит-тестируются здесь как механика состояния; решения об их оркестрации (какие переходы допустимы, истечение временных банов, взаимодействие с подтверждением почты) принимаются в Application-плане.

## Контекст

- Рамка: модульный монолит Spiral + Cycle ORM, PHP 8.5, тактический DDD (`docs/arch.md:5`, `docs/arch.md:13`). Границы между модулями — только через `Application` (`docs/arch.md:131`); в этом плане межмодульного кода нет (`Access` ссылается на пользователя только общим `UserId`).
- Эталон — модуль `Media`: Entity с property hooks + `#[Column(typecast:)]`, `create()`, `HasTimestamps` (`app/src/Modules/Media/Domain/Entity/Media.php`); per-VO typecast-классы `ColumnValueTypecast` для nullable/null-object/datetime (`MediaProcessingErrorTypecast`, `MediaExpirationTypecast`); репозиторий `@extends Repository<Media>`.
- Общие базовые VO уже есть: `UserId`, `AbstractUuidV7Id`, `AbstractIntegerValue` в `app/src/Shared/Domain/ValueObject/`. `UserId extends AbstractUuidV7Id` (UUID v7, `generate()/fromString()/value()/equals()`, `Stringable`+`JsonSerializable`).
- enum `Locale` в `Shared/Domain` **не существует** — локали сейчас только в `LocaleConfig` (`app/src/Shared/Infrastructure/Configuration/Locale/LocaleConfig.php`, `app/config/locale.php`, поддерживаются `ru`/`en`).
- `ext-intl` **уже установлен** в Docker-образе (`docker/Dockerfile`, `php8.5-intl`); в `composer.json` его в `require` нет.
- Cycle-сущности находятся авто-дискавери по `#[Entity]` (`AnnotatedBootloader` в `Kernel.php`) — ручная регистрация Entity не нужна; репозитории резолвятся cycle-bridge автоматически. Бутлоадеры в этом плане не добавляются (нет Application/console).
- Тесты: `tests/Unit` (без БД), `tests/Feature` (с БД, базовый `Tests\DatabaseTestCase` — транзакция в `setUp`, rollback в `tearDown`, очистка ORM heap), `tests/Kernel`. Команды: `make test`, `make test-feature`, `make test-coverage` (PCOV, порог 100%), `make phpstan`, `make migrate`.

## Принятые решения

Для существенных решений указан источник подтверждения.

1. **Объём — только слой данных/домена.** Application (хендлеры, консоль, сидер, аватар-поток, RBAC-проверки, оркестрация банов) и HTTP — отдельный план. _Источник: ответ пользователя в текущем сообщении._
2. **Два модуля, без межмодульного кода в этом плане.** `Access` ссылается на пользователя через общий `UserId` из `Shared`, не зная Entity `User`. Кросс-модульный поток аватара (`User`→`Media/Application`) — в Application-плане. _Источник: research + `docs/arch.md:131`._
3. **Уникальность email/nickname — `string` + обычный `UNIQUE`-индекс + нормализация в VO (lower).** VO приводят значение к нижнему регистру до записи; обычный UNIQUE выражается штатным Cycle schema builder без сырого SQL и расширений. Регистронезависимость гарантируется на уровне приложения (записи в обход VO не защищены БД — известное ограничение). _Источник: ответ пользователя._
4. **Строгая NFC-нормализация имён через `ext-intl`.** Текстовые VO (`UserName`, `UserSpiritualName`) применяют `\Normalizer::normalize($v, \Normalizer::FORM_C)` с guard на результат `=== false` → `InvalidDomainValueException`; длина считается через `mb_strlen`, не `strlen`. В `composer.json` добавляется `"ext-intl": "*"` (platform-расширение, уже в Docker-образе). _Источник: ответ пользователя + cross-CLI ревью._
5. **`Locale` — новый enum в `Shared/Domain/Enum/Locale`** (`Ru = 'ru'`, `En = 'en'`), закрытый набор → enum (`docs/rules.md:30`). Набор обязан совпадать с `LocaleConfig.supported` (проверяется тестом). Гидрация из БД — через `ValueObjectCast` ветку `BackedEnum::from`; `fromString()` — для построения из примитива (в будущем Application). _Источник: `decision_mode: autonomous` по rules.md:30; расходится с research, где enum предполагался существующим._
6. **Все идентификаторы — UUID v7, включая связки.** `user_roles` и `role_permissions` получают собственный UUID v7 PK (`UserRoleId`/`RolePermissionId`) + `UNIQUE`-индекс на натуральную пару, а не составной PK. _Источник: `docs/rules.md:31` (расходится с research «PK составной»)._
7. **Связки RBAC — явные link-Entity (`UserRole`, `RolePermission`) + репозитории с доменными методами; без Cycle ManyToMany.** Разворачивание «пользователь → роли → права» — задача Application-плана. _Источник: `docs/arch.md:465`, `docs/rules.md:66`._
8. **Мягкое удаление пользователя.** `deleted_at` + доменный метод `markDeleted()` (`status = Deleted`), строка не удаляется. `email`/`nickname` на старте не освобождаются. _Источник: research._
9. **Кросс-модульные FK на уровне БД допустимы** (одна БД монолита): `user_bans.user_id`→`users` CASCADE, `user_roles.user_id`→`users` CASCADE, `avatar_media_id`→`media` RESTRICT, `reserved_nicknames.assigned_user_id`→`users` SET NULL. В коде `Access` не обращается к таблицам/репозиториям `User`. `user_bans.banned_by_id` — намеренно без FK (аудит-ссылка). _Источник: research (стр. 200) + `docs/arch.md:141-146`._
10. **Зарезервированные никнеймы — таблица в модуле `User` с отметкой владельца.** `reserved_nicknames` (`id`, `nickname` UNIQUE, `assigned_user_id` nullable → FK `users` SET NULL, timestamps) + Entity `ReservedNickname` с null-object VO `ReservedNicknameHolder` (`unassigned()`/`assignedTo(UserId)`, `isAssignedTo()`). Логика блокировки регистрации/выдачи — задача Application-плана; здесь — таблица, Entity и доменные методы. _Источник: ответ пользователя._
11. **Время — только UTC; таймзону пользователя не храним.** Колонки `timezone`/VO `UserTimezone` нет. Все даты в БД — UTC; обмен датами всегда с явной таймзоной/смещением (ISO 8601 с offset). Соглашение добавить в `docs/rules.md` отдельной задачей `eda-docs`. _Источник: ответ пользователя._
12. **Ошибки VO — `InvalidDomainValueException` (500).** Невалидные доменные значения в VO бросают `InvalidDomainValueException` (`docs/rules.md:40`). Клиентские 4xx-исключения (`ValidationException`/`NotFoundException`) и их ключи переводов — задача Application-плана. _Источник: `docs/rules.md:40`._

Ожидаемый объём: 4 фазы; ~55–70 новых файлов (VO, enum, Entity, typecast, репозитории, коллекции, 2 миграции) + тесты; покрытие 100%.

## Доменное поведение

Поведение, реализуемое доменными методами Entity (без оркестрации — это механика состояния, вызовы методов проверяются юнит-тестами):

- **`User`**: `create(UserName, Email, UserNickname, Locale)` → `status = WaitingEmailConfirmation`, `verification = Unverified`, опциональные поля = null-object `none()`, таймстампы. `confirmEmail()` → `Active`. `changeEmail(Email)` → сбрасывает `status = WaitingEmailConfirmation`. `changeNickname(UserNickname)`. `rename/changeSpiritualName/changeBio/changeLocation/changeLocale` меняют соответствующее поле и `touch()`. `setAvatar(UserAvatar)`/`removeAvatar()`. `verify()`/`unverify()` переключают бейдж. `ban()` → `Banned`, `unban()` → `Active`. `markDeleted(now)` → `deletion = UserDeletion::at(now)`, `status = Deleted`. Чистые predicate `isActive/isVerified/isBanned/isDeleted`. _Допустимость переходов и взаимодействие (бан неактивных, восстановление статуса при разбане, истечение временных банов) решает Application-план._
- **`UserBan`**: `create(...)` с `userId/bannedById/reason/expiration`. `markUnbanned(BanUnbannedBy, BanUnbannedAt, BanUnbannedReason)`. Predicate `isActive(now)` = не снят (`unbanned_at` пуст) и не истёк (`expiration` permanent или в будущем).
- **`ReservedNickname`**: `create(UserNickname)` (holder `unassigned()`); `assignTo(UserId)`; predicate `isAssignedTo(UserId)`.
- **`Access`**: `Role`/`Permission` хранят `slug`; `RolePermission`/`UserRole` — строки-связки (`create(...)`). Разворачивание прав — Application-план.

Намерения запросов репозиториев перечислены в «Контрактах»; их корректность проверяется Feature-тестами round-trip.

## Контракты реализации

### Данные и БД

Две новые миграции (формат `app/database/migrations/{YYYYMMdd.HHMMSS}_0_*.php`, `namespace Migration;`, `class … extends Migration` — короткое имя как в эталоне `20260521.184100_0_create_media_domain_tables.php`, `protected const DATABASE = null`, методы `up()/down()`). Порядок: `CreateUserDomainTables` раньше `CreateAccessDomainTables` (FK Access ссылаются на `users` и `roles`); обе позже существующей миграции `media` (имена файлов лексикографически: `20260521…` < новых). Проверить накат на пустой БД в порядке `media → users → access`.

Миграция 1 — `CreateUserDomainTables`:

```text
users
  id                uuid    PK
  name              string(100)  not null
  spiritual_name    string(100)  null
  bio               text         null
  location          string(100)  null
  email             string(254)  not null      UNIQUE(email)
  nickname          string(30)   not null      UNIQUE(nickname)
  avatar_media_id   uuid         null          FK -> media(id) ON DELETE RESTRICT ON UPDATE CASCADE
  verification      string(32)   not null
  status            string(32)   not null      index(status)
  locale            string(8)    not null
  deleted_at        datetime     null
  created_at        datetime     not null
  updated_at        datetime     not null

user_bans
  id              uuid    PK
  user_id         uuid    not null    index(user_id)   FK -> users(id) ON DELETE CASCADE
  banned_by_id    uuid    not null                     (без FK — аудит-ссылка)
  reason          string(500)  not null
  expires_at      datetime     null
  unbanned_at     datetime     null
  unbanned_by_id  uuid         null
  unbanned_reason string(500)  null
  created_at      datetime     not null
  updated_at      datetime     not null

reserved_nicknames
  id                uuid  PK
  nickname          string(30)  not null   UNIQUE(nickname)
  assigned_user_id  uuid        null       index(assigned_user_id)  FK -> users(id) ON DELETE SET NULL
  created_at        datetime    not null
  updated_at        datetime    not null
```

Миграция 2 — `CreateAccessDomainTables`:

```text
roles
  id          uuid  PK
  slug        string(64)  not null   UNIQUE(slug)
  created_at  datetime    not null
  updated_at  datetime    not null

permissions
  id          uuid  PK
  slug        string(64)  not null   UNIQUE(slug)
  created_at  datetime    not null
  updated_at  datetime    not null

role_permissions
  id             uuid  PK
  role_id        uuid  not null   FK -> roles(id) ON DELETE CASCADE
  permission_id  uuid  not null   FK -> permissions(id) ON DELETE CASCADE
  UNIQUE(role_id, permission_id)        (без created_at/updated_at)

user_roles
  id        uuid  PK
  user_id   uuid  not null   FK -> users(id) ON DELETE CASCADE
  role_id   uuid  not null   FK -> roles(id) ON DELETE CASCADE
  UNIQUE(user_id, role_id)              (без created_at/updated_at)
```

Индексы/FK через schema builder: `addIndex([...], ['unique' => true])`, `addForeignKey([col], 'table', ['id'], ['delete' => 'CASCADE'|'RESTRICT'|'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false])`, `setPrimaryKeys(['id'])`. Сырой SQL не используется.

**Entity (свойства = VO/enum, property hooks `public private(set)`, `create()` + доменные методы):**

- `User` (`Modules/User/Domain/Entity/User.php`, `role: 'user'`, `table: 'users'`, `typecast: [Typecast::class, ValueObjectCast::class]`, `use HasTimestamps`): `id: UserId`, `name: UserName`, `spiritualName: UserSpiritualName`, `bio: UserBio`, `location: UserLocation`, `email: Email`, `nickname: UserNickname`, `avatar: UserAvatar`, `verification: UserVerification`, `status: UserStatus`, `locale: Locale`, `deletion: UserDeletion`. Методы — см. «Доменное поведение» (включая `changeLocale(Locale)`).
- `UserBan` (`role: 'user_ban'`, `table: 'user_bans'`, `use HasTimestamps`): `id: UserBanId`, `userId: UserId`, `bannedById: UserId`, `reason: BanReason`, `expiration: BanExpiration`, `unbannedAt: BanUnbannedAt`, `unbannedBy: BanUnbannedBy`, `unbannedReason: BanUnbannedReason`. Методы: `create(...)`, `markUnbanned(BanUnbannedBy, BanUnbannedAt, BanUnbannedReason)`, `isActive(now)`.
- `ReservedNickname` (`role: 'reserved_nickname'`, `table: 'reserved_nicknames'`, `use HasTimestamps`): `id: ReservedNicknameId`, `nickname: UserNickname`, `holder: ReservedNicknameHolder`. Методы: `create(UserNickname)`, `assignTo(UserId)`, `isAssignedTo(UserId)`.

Все Entity `Access` подключают `typecast: [Typecast::class, ValueObjectCast::class]` (иначе VO/uuid не гидрируются). `Role`/`Permission` — `use HasTimestamps`; link-Entity `RolePermission`/`UserRole` — **без `HasTimestamps`** (в таблицах нет `created_at`/`updated_at`):
- `Role` (`roles`): `id: RoleId`, `slug: RoleSlug`. `create(RoleSlug)`.
- `Permission` (`permissions`): `id: PermissionId`, `slug: PermissionSlug`. `create(PermissionSlug)`.
- `RolePermission` (`role_permissions`): `id: RolePermissionId`, `roleId: RoleId`, `permissionId: PermissionId`. `create(RoleId, PermissionId)`.
- `UserRole` (`user_roles`): `id: UserRoleId`, `userId: UserId`, `roleId: RoleId`. `create(UserId, RoleId)`.

**Value Object и enum:** все скалярные VO — `final readonly`, `Stringable`, `JsonSerializable`, приватный конструктор, фабрика с валидацией, `value()`, `equals()`, бросают `InvalidDomainValueException`. null-object VO добавляют `none()`/`active()`/`permanent()`/`unassigned()` и predicate, `value()` возвращает nullable. Длина текстовых полей — через `mb_strlen`. NFC-нормализация (`UserName`/`UserSpiritualName`) — `\Normalizer::normalize(..., FORM_C)` с guard `=== false` → `InvalidDomainValueException`.

```text
Shared/Domain/Enum:  Locale (Ru='ru', En='en')

User/Domain/ValueObject:
  Email                 trim+lower, filter_var RFC, <=254 (mb_strlen), не пусто
  UserNickname          lower, ^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$, без '..', 3-30
  UserName              NFC FORM_C (guard !==false), whitelist \p{L}\p{M} + ' -' ', 1-100 (mb_strlen), схлоп. пробелов
  UserSpiritualName     null-object, правила UserName, none()
  UserBio               null-object, без \p{Cc} кроме \n, эмодзи ок, <=500 (mb_strlen), none()
  UserLocation          null-object, whitelist \p{L}\p{N} + ' .,-', без \p{So}\p{Cs}, <=100 (mb_strlen), none()
  UserAvatar            null-object: none()/pointingTo(string uuidV7); value(): ?string
  UserDeletion          null-object: active()/at(\DateTimeImmutable); isDeleted()
  BanReason             trim, не пусто, <=500 (mb_strlen)
  BanExpiration         null-object: permanent()/until(\DateTimeImmutable); isPermanent()
  BanUnbannedAt         null-object: notUnbanned()/at(\DateTimeImmutable); isUnbanned()
  BanUnbannedBy         null-object: none()/by(UserId)
  BanUnbannedReason     null-object: none()/of(string); trim, не пусто, <=500; value(): ?string
  UserBanId             extends AbstractUuidV7Id
  ReservedNicknameId    extends AbstractUuidV7Id
  ReservedNicknameHolder  null-object: unassigned()/assignedTo(UserId); isAssignedTo(UserId); value(): ?string
User/Domain/Enum:
  UserStatus: string    WaitingEmailConfirmation | Active | Banned | Deleted
  UserVerification: string  Unverified | Verified

Access/Domain/ValueObject:
  RoleId, PermissionId, UserRoleId, RolePermissionId   extends AbstractUuidV7Id
  RoleSlug, PermissionSlug   ^[a-z][a-z0-9.]*[a-z0-9]$
Access/Domain/Enum:
  RoleName: string        Admin = 'admin'                 (метод slug(): RoleSlug)
  PermissionName: string  UserBan='user.ban', UserVerify='user.verify',
                          UserRoleAssign='user.role.assign'   (метод slug(): PermissionSlug)
```

`RoleName`/`PermissionName` — канон для будущего сидера (Application-план); сами строки `roles`/`permissions` в этом плане не сидятся.

**Typecast (`Modules/User/Infrastructure/Cycle/*Typecast` — `ColumnValueTypecast`, статические `castDatabaseValue()/uncastValue()`):** для nullable-колонок при non-null VO-свойствах — `UserSpiritualNameTypecast`, `UserBioTypecast`, `UserLocationTypecast`, `UserAvatarTypecast`, `UserDeletionTypecast` (datetime↔null-object), `BanExpirationTypecast` (datetime), `BanUnbannedAtTypecast` (datetime), `BanUnbannedByTypecast` (uuid), `BanUnbannedReasonTypecast` (text), `ReservedNicknameHolderTypecast` (uuid). Каждый: `castDatabaseValue(null)` → соответствующий null-object (`none()`/`active()`/`permanent()`/`notUnbanned()`/`unassigned()`), не-null → VO; `uncastValue` всегда возвращает скаляр/`DateTimeImmutable` или `null`. Подключаются на колонках через `#[Column(typecast: XTypecast::class)]`. Non-nullable VO и enum (`UserId`, `Email`, `UserNickname`, `UserName`, `Locale`, `UserStatus`, `UserVerification`, `BanReason`, `banned_by_id`, все VO `Access`) — общий `ValueObjectCast` по соглашению (`fromString`/`BackedEnum::from`/`value()`). Отдельных typecast-классов `Access` не требует.

**Коллекции** (`final`, `@extends Collection<int, T>`): `RoleCollection`, `PermissionCollection`, `UserRoleCollection`, `RolePermissionCollection` (возвращаются методами репозиториев `findByIds`/`findAll`/`findByUserId`/`findByRoleId`).

**Репозитории** (`@extends Repository<Entity>`, read-only):

```text
User/Repository/UserRepository:     findById(UserId): ?User; findByEmail(Email): ?User;
                                    findByNickname(UserNickname): ?User;
                                    existsByEmail(Email): bool; existsByNickname(UserNickname): bool
User/Repository/UserBanRepository:  findById(UserBanId): ?UserBan;
                                    findActiveByUserId(UserId, \DateTimeImmutable now): ?UserBan
User/Repository/ReservedNicknameRepository: findByNickname(UserNickname): ?ReservedNickname;
                                    isReserved(UserNickname): bool
Access/Repository/RoleRepository:        findById(RoleId): ?Role; findBySlug(RoleSlug): ?Role;
                                        findByIds(RoleId ...$ids): RoleCollection; findAll(): RoleCollection
Access/Repository/PermissionRepository:  findById(PermissionId): ?Permission; findBySlug(PermissionSlug): ?Permission;
                                        findByIds(PermissionId ...$ids): PermissionCollection; findAll(): PermissionCollection
Access/Repository/UserRoleRepository:    findByUserId(UserId): UserRoleCollection; exists(UserId, RoleId): bool
Access/Repository/RolePermissionRepository: findByRoleId(RoleId): RolePermissionCollection; exists(RoleId, PermissionId): bool
```

`findActiveByUserId` — активный бан = `unbanned_at IS NULL AND (expires_at IS NULL OR expires_at > now)`. Это `OR` внутри `AND`, поэтому вторую часть **обязательно** группировать closure (иначе Cycle сгенерирует неверный SQL без скобок): `select()->where('user_id', $userId->value())->where('unbanned_at', '=', null)->where(static fn (\Cycle\Database\Query\SelectQuery $q) => $q->where('expires_at', '=', null)->orWhere('expires_at', '>', $now))->fetchOne()`. `DateTimeImmutable` передаётся в `where` напрямую (как `MediaRepository::findExpired`).

### API и внешние контракты

`Не затрагивается` — ни HTTP, ни Application-слой в этом плане не реализуются.

## Фазы выполнения

### 1. Общая основа: ext-intl и enum Locale
Цель: подготовить общие зависимости до доменного кода.

Что сделать:
- В `composer.json` добавить `"ext-intl": "*"` в `require` (рядом с прочими `ext-*`); обновить `composer.lock` (`composer update --lock`). Docker-образ менять не нужно (intl уже стоит).
- Создать `app/src/Shared/Domain/Enum/Locale.php` (`enum Locale: string { case Ru = 'ru'; case En = 'en'; }`) с фабрикой `fromString()` (ловит `\ValueError` от `from()` → `InvalidDomainValueException`). Гидрация из БД — через `ValueObjectCast`/`BackedEnum::from`, отдельный typecast не нужен.

Результат: `ext-intl` доступен, enum `Locale` готов к использованию в `User`.

Сценарии тестирования:
- `Locale::fromString('ru')`/`'en'` → корректно; неизвестное → `InvalidDomainValueException`.
- Синхронность канона: значения `Locale::cases()` совпадают с `LocaleConfig.supported` (Kernel-тест через `ConfigMapper`) — защита от расхождения enum и конфига.

Проверка: `make phpstan`; `make test`; `make test-coverage` 100% для созданных файлов.

### 2. Модуль User: домен (VO, enum, Entity)
Цель: чистый доменный слой `User` без инфраструктуры.

Что сделать:
- Создать все VO `User/Domain/ValueObject` и enum `User/Domain/Enum` из «Контрактов». Текстовые имена: `trim` + схлопывание пробелов + `\Normalizer::normalize(..., FORM_C)` с guard `=== false` → `InvalidDomainValueException`; запреты — whitelist через PCRE `\p{...}`; длина — `mb_strlen`.
- Создать Entity `User`, `UserBan`, `ReservedNickname` с property hooks, `create()` и доменными методами (см. «Доменное поведение»). Вся валидация — в VO, Entity её не дублирует.

Результат: домен `User` компилируется, покрыт unit-тестами; примитивов в Entity нет.

Сценарии тестирования (unit, без БД):
- Каждый VO: валидное значение, граничные длины (через `mb_strlen`), невалидное → `InvalidDomainValueException`, нормализация (lower для email/nickname, NFC для имён, схлопывание пробелов, guard на `Normalizer===false`), `equals()`, `none()`/predicate для null-object.
- enum `UserStatus`/`UserVerification`: исчерпывающий набор.
- Entity: покрыть **каждый** доменный метод напрямую (иначе осиротевший метод → красный гейт): `create()` дефолты (`WaitingEmailConfirmation`, `Unverified`, null-object `none()`); `rename`/`changeSpiritualName`/`changeBio`/`changeLocation`/`changeLocale`/`changeNickname` меняют поле и `touch()`; `changeEmail` → `WaitingEmailConfirmation`; `confirmEmail`→`Active`; `setAvatar`/`removeAvatar`; `ban`/`unban`/`verify`/`unverify`/`markDeleted`; predicate `isActive/isVerified/isBanned/isDeleted`; `UserBan::create`/`markUnbanned`/`isActive(now)` (три ветки: permanent, срочный непросроченный, срочный просроченный); `ReservedNickname::create`(holder `unassigned`)/`assignTo`/`isAssignedTo`.

Проверка: `make phpstan`; `make test`; `make test-coverage` 100%.

### 3. Модуль User: персистентность (миграция, typecast, репозитории)
Цель: round-trip `User`/`UserBan`/`ReservedNickname` через БД.

Что сделать:
- Миграция `CreateUserDomainTables` (`users`, `user_bans`, `reserved_nicknames`) по схеме из «Контрактов».
- Typecast-классы `User/Infrastructure/Cycle/*Typecast` для nullable/null-object/datetime/uuid-колонок; на Entity — `typecast: [Typecast::class, ValueObjectCast::class]` и `#[Column(typecast: ...)]`.
- Репозитории `UserRepository`, `UserBanRepository`, `ReservedNicknameRepository` с доменными методами из «Контрактов».

Результат: сущности сохраняются/читаются с корректной гидрацией VO (включая null-object, datetime, uuid-holder); запросы репозиториев работают.

Сценарии тестирования:
- **Unit на typecast-классы** (round-trip через БД не покрывает все ветки): `castDatabaseValue(null)` → нужный null-object; не-null → VO; `uncastValue(null-object)` → `null`; `uncastValue(set)` → скаляр/`DateTimeImmutable`; по образцу typecast-ов `Media`.
- Feature (`DatabaseTestCase`): persist/load `User` с заполненными и пустыми (`none()`) опциональными полями — идентичное восстановление; `deleted_at` ↔ `UserDeletion`. persist/load `UserBan` (`permanent()`/`until(dt)`, снятый/не снятый — round-trip `unbanned_at`/`unbanned_by_id`/`unbanned_reason`). `findByEmail`/`findByNickname`/`findById`, `existsByEmail`/`existsByNickname` (регистронезависимо), `findActiveByUserId` (permanent активный / срочный непросроченный активный / срочный просроченный → null / снятый → null). persist/load `ReservedNickname` с holder `unassigned()` и `assignedTo(UserId)`; `isReserved`/`findByNickname`. FK `assigned_user_id`→`users`.
- UNIQUE-индекс: вставка дубликата email/nickname/зарезервированного ника падает — **отдельным тест-методом без последующих запросов** (нарушение constraint «отравляет» транзакцию PostgreSQL).

Проверка: `make migrate` на пустой БД проходит в порядке `media → users` (FK `avatar_media_id`→`media`); `make test-feature`; `make phpstan`; `make test-coverage` 100%.

### 4. Модуль Access: домен и персистентность
Цель: справочники ролей/прав и связки с round-trip через БД.

Что сделать:
- VO `Access/Domain/ValueObject` (`RoleId`, `PermissionId`, `UserRoleId`, `RolePermissionId`, `RoleSlug`, `PermissionSlug`), enum `RoleName`/`PermissionName` со `slug()`-методами, Entity `Role`/`Permission`/`RolePermission`/`UserRole` (с `typecast: [Typecast::class, ValueObjectCast::class]`; таймстампы только у `Role`/`Permission`), коллекции.
- Миграция `CreateAccessDomainTables` (`roles`, `permissions`, `role_permissions`, `user_roles`) с UNIQUE на slug и на натуральные пары, FK CASCADE (включая `user_roles.user_id`→`users`).
- Репозитории `RoleRepository`, `PermissionRepository`, `UserRoleRepository`, `RolePermissionRepository`. Typecast — общий `ValueObjectCast` (все VO non-nullable).

Результат: модель `Access` сохраняется/читается; репозитории дают доменные методы.

Сценарии тестирования:
- unit: VO slug (валидные `admin`/`user.ban`, невалидные), enum→slug.
- Feature (`DatabaseTestCase`): persist/load `Role`/`Permission`/`RolePermission`/`UserRole`; `findBySlug`/`findByIds`/`findAll`; `UserRoleRepository::findByUserId`/`exists`; `RolePermissionRepository::findByRoleId`/`exists`; UNIQUE на парах падает при дубле (отдельным методом). Для `UserRole` сперва создать `User`-фикстуру (FK `user_roles.user_id`→`users` `NOT NULL` проверяется на вставке).

Проверка: `make migrate`; `make test` (полный сьют); `make phpstan`; `make test-coverage` 100%.

## Тесты

Стратегия: `after_each_phase`. В каждой фазе тесты пишутся и прогоняются сразу после реализации; фаза готова только при зелёных `make phpstan`, `make test`/`make test-feature` и 100% покрытии созданного кода (`make test-coverage`) — обязательно в каждой фазе, где добавлен код в `app/src`. Unit-тесты (VO, enum, доменные методы Entity, typecast-классы) — в `tests/Unit`; Feature-тесты персистентности/репозиториев — в `tests/Feature/Modules/{User,Access}/...` на базе `Tests\DatabaseTestCase` (новые вспомогательные базовые классы для модулей наследуются именно от `DatabaseTestCase`, чтобы состояние БД не протекало между тестами). HTTP-роутов и Application-хендлеров нет, поэтому route- и handler-тестов в этом плане нет (появятся в Application-плане).

Особенность: тест на нарушение UNIQUE-индекса — отдельным тест-методом без последующих запросов после ожидаемого исключения (нарушение constraint в PostgreSQL «отравляет» транзакцию, иначе flaky).

## Логирование

Стратегия: `debug_precise`. В этом плане **кода с логированием нет**: домен (VO/Entity) и репозитории не пишут логи. Стратегия `debug_precise` (`#[LogOperation]` на хендлерах, точечные DEBUG в местах решений, INFO на бизнес-событиях) применяется в Application-плане, где появятся Handler-ы.

## Документация и эксплуатация

- `composer.json`/`composer.lock`: добавлен `ext-intl` (в Docker-образе расширение уже присутствует, dev/CI изменений не требуют).
- Соглашение «время только в UTC, обмен датами с явной таймзоной» (решение 11) добавить в `docs/rules.md` отдельной задачей `eda-docs`.
- Известные ограничения слоя данных: регистронезависимость `email`/`nickname` гарантируется только на уровне приложения (нормализация в VO), без `citext`/функционального индекса — записи в обход VO не защищены БД; `email`/`nickname` удалённого пользователя не освобождаются; `user_bans.banned_by_id` без FK (аудит); FK `reserved_nicknames.assigned_user_id` `ON DELETE SET NULL` при soft-delete фактически инертен (строка пользователя не удаляется) — оставлен как защита на будущее.
- **Следующий план (Application-слой)**, который опирается на этот: Command/Query + Handler-ы (создание/обновление профиля, смена email/nickname/локали, подтверждение почты, бан/разбан, удаление, резервирование/выдача ников, назначение ролей, проверка прав), сидер RBAC из enum-канона, кросс-модульный поток аватара (`User`→`Media/Application`), консольные команды и их бутлоадеры, файлы переводов `app.user.*`/`app.access.*`, оркестрация банов. Туда же — нерешённые в этом объёме вопросы из ревью (см. «Реакция на ревью»).

## Изменения после мета-ревью

### После моделей (haiku / sonnet / opus)

Применено к слою данных/домена:
- **+ Добавлено:** closure-группировка `OR` в `findActiveByUserId` + три тест-ветки; явный контракт typecast-классов (`null`↔null-object, `uncast` возвращает скаляр/`null`) + их unit-тесты; синхронность `Locale::cases()` ↔ `LocaleConfig.supported`; link-Entity `RolePermission`/`UserRole` без `HasTimestamps`; `typecast: [Typecast, ValueObjectCast]` на Entity `Access`; `User`-фикстура для FK `user_roles`; изоляция теста на UNIQUE-конфликт.
- **~ Изменено:** `findByIds(string …)` → `findByIds(RoleId/PermissionId …)` (`docs/rules.md:36-37`); unit-тесты фазы 2 покрывают **каждый** доменный метод Entity; гидрация `Locale` через `BackedEnum::from`; `make test-coverage` 100% обязателен в каждой фазе.
- **Перенесено в Application-план** (после сужения объёма): инвариант порядка `persist` и тест отката для `SetUserAvatar`, `CreateUser`/`UpdateUserProfile`/`UserResult`/`LocaleConfig`-инъекция, логирование `SyncRbac` на DEBUG, логика резерва при регистрации, статус-матрица команд — это Application.

## Реакция на ревью

Кросс-CLI ревью (`codex`, файл `docs/plans/2026-06-13_14-39_user-access-domain_review.md`).

Внесено в план (слой данных/домена):
- Link-Entity `user_roles`/`role_permissions` без `HasTimestamps` (нет таймстамп-колонок).
- Guard на `Normalizer::normalize() === false`; длина через `mb_strlen` для всех Unicode-полей.
- Unit-тесты typecast-классов по образцу `Media` (ветки `null`/значение/`uncast(null)`).
- closure-группировка `OR` в `findActiveByUserId`.
- Добавлен доменный метод `User::changeLocale(Locale)` (доменная часть замечания про смену локали).

Вынесено в отдельный Application-план (вне объёма данных/домена, по решению пользователя):
- Оба блокера по банам: застревание временного бана после `expires_at` (нужен механизм истечения) и обход подтверждения почты при разбане (`unban` всегда `Active`) — это оркестрация в Handler-ах.
- BanExpiration в прошлом → guard в `BanUserHandler`; self-ownership при смене email/nickname (`findBy…` + сравнение `userId`); семантика очистки полей в `UpdateUserProfile`; команда смены локали; проверка существования пользователя в `AssignReservedNickname`/`AssignRoleToUser`; контракт `PermissionName` для `CheckUserHasPermission`; обнуление аватара при `DeleteUser` (FK RESTRICT); регистрация консольных бутлоадеров (в секции после `Framework\CommandBootloader`, не рядом с `MediaBootloader`); матрица статусов в тестах команд; файлы переводов `app.user.*`/`app.access.*` + тест; политика очистки устаревших прав в `SyncRbac`; статус-агностичность `CheckUserHasPermission` (фильтрацию удалённых/забаненных делает будущий Auth).

Отклонено: существенных отклонённых замечаний нет — всё либо внесено, либо обоснованно перенесено в Application-план.

## Прогресс выполнения

Журнал: `docs/executions/2026-06-13_16-41_user-access-domain.md`

- [x] Шаг 1: Общая основа: ext-intl и enum Locale
- [x] Шаг 2: Модуль User: домен (VO, enum, Entity)
- [x] Шаг 3: Модуль User: персистентность (миграция, typecast, репозитории)
- [x] Шаг 4: Модуль Access: домен и персистентность
