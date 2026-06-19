---
plan: docs/plans/2026-06-18_17-42_posts-application-presentation.md
started: 2026-06-19 12:51
finished: 2026-06-19
status: done
---

# Журнал: Модуль Posts — прикладной и презентационный слой + уведомления

## Контекст старта

В рабочем каталоге `work-1` фактически готовы только `Domain`/`Infrastructure`/`Repository`
модулей Posts/Tags (коммит `2f3bc87`). Папок `Application`/`Presentation`/`Infrastructure/Bootloader`,
межмодульных сценариев и конфига `user.php` нет. Чекбоксы фаз 1–2 в плане были помечены `[x]`
преждевременно (журнал, на который ссылался план, не существовал). По подтверждению пользователя
исполняем весь план с фазы 1, чекбоксы сброшены.

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Каркас Posts, `PostsBootloader`, 7 видов уведомлений (`PostNotificationType` enum), `NotificationContentBuilder`, переводы `posts.php` ru/en, регистрация в Kernel | `app/src/Modules/Posts/Application/Notification/*`, `app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php`, `app/locale/{ru,en}/posts.php`, `Kernel.php` | `tests/Kernel/Modules/Posts/Notification/*` | done |
| 2 | Конфиг `UserConfig`+`app/config/user.php`; User `GetUserPublicProfile(s)`+assembler+`UserCollection`+`findByIds`; Media `CheckMediaAttachable`, `FindMediaUrl`; Tags `ResolveTags`, `GetTags`, `findByIds` | `app/src/Modules/{User,Media,Tags}/Application/**`, `Shared/Infrastructure/Configuration/User/UserConfig.php`, `app/config/user.php`, `.env.sample` | `tests/Feature/Modules/{User,Media,Tags}/Application/*`, `tests/Kernel/.../UserConfigTest.php` | done |
| 3 | Записи Create/Publish/Repost/Delete: команды+хендлеры, `PostContentComposer`, `PostViewAssembler`, `PostVisibilityPolicy`, `PostNotifier`, read-model `PostView/AuthorView/PostMediaItemView/TagView`, `PostController`+4 фильтра+ресурсы (Post/Author/Media/Tag) | `app/src/Modules/Posts/Application/{Command,Post,View,Notification}/**`, `app/src/Modules/Posts/Presentation/Http/**` | `tests/Feature/Modules/Posts/Http/{Create,Publish,Repost,Delete}PostHttpTest.php` (+`PostsHttpTestCase`) | done |

Заметки фазы 3:
- 4 роута записей (POST `/posts`, POST `/posts/<id>/publish`, POST `/posts/<id>/repost`, DELETE `/posts/<id>`). Like/Unlike записи — в фазе 4 (реакции).
- Тесты HTTP: `DatabaseTestCase` (откат) + инъекция `authUserId` (auth-middleware его не затирает) + `RecordingOutboxEventStore` для проверки стейджинга + стаб `MediaFileServiceContract`. 35 тестов зелёные, PHPStan зелёный.
- `attachmentType` записи выводится из наличия `mediaIds` (none/media), отдельное поле фильтра не вводилось (lesson/practice отложены, решение 21).

| 4 | Реакции и комментарии: команды+хендлеры LikePost/UnlikePost/CommentPost/ReplyComment/DeleteComment/LikeComment/UnlikeComment, `CommentComposer` (дедуп уведомлений mention>reply>commented), `CommentViewAssembler`, `CommentView/CommentResource`, like/unlike в `PostController`, новый `CommentController`+7 фильтров | `app/src/Modules/Posts/Application/Command/**`, `Posts/Application/Post/CommentComposer.php`, `Posts/Presentation/Http/{Controller/CommentController,Filter/*,Resource/CommentResource}.php` | `tests/Feature/Modules/Posts/Http/{PostReaction,Comment,CommentReaction}HttpTest.php` | done |

Заметки фазы 4:
- Like/Unlike записи — в `PostController` (роуты `/posts/<id>/like`); комментарии и их реакции — в `CommentController` (роуты `/posts/<id>/comments`, `/posts/comments/<id>/...`).
- Уточнение по терминологии (по замечанию пользователя): аутентификация на роутах Posts — непрозрачные Bearer-токены из БД (`storage: 'cycle'`, `CycleTokenStorage`, таблица `auth_tokens`), НЕ JWT. Комментарии в фильтрах `PublishPostFilter`/`DeletePostFilter` исправлены с «JWT-атрибут» на «request-атрибут, выставленный AuthContextAttributeMiddleware».
- Дедуп уведомлений на действие: на одного получателя одно уведомление с приоритетом mention > comment_reply > post_commented; self-получатель исключается в `PostNotifier`. 30 HTTP-тестов зелёные, PHPStan зелёный.

| 5 | Чтение: read-методы репозиториев (`findVisibleByUserId`, `findTopLevelByPostId`, фильтр удалённых в `findReplies`, батч `findByUserAndPostIds`/`findByUserAndCommentIds`), Query GetPost/GetUserFeed/GetPostComments/GetCommentReplies, батч-сборка `PostViewAssembler`/`CommentViewAssembler`, read-методы контроллеров + 4 фильтра чтения | `Posts/Repository/*`, `Posts/Application/Query/**`, `Posts/Application/View/*Assembler.php`, `Posts/Presentation/Http/{Controller,Filter}/*` | `tests/Feature/Modules/Posts/Http/{GetPost,GetUserFeed,GetPostComments,GetCommentReplies}HttpTest.php`, `LikeBatchRepositoryTest.php` | done |

Заметки фазы 5:
- Cursor-пагинация (limit+1, nextCursor) как в `ListNotificationsHandler`. Лента: владельцу — все статусы (null), постороннему — только Published; soft-deleted скрыты репозиторием.
- Авторы и флаги likedByMe в листингах собираются пакетно (батч-методы лайков + `GetUserPublicProfiles`), без N+1.
- Для 100% покрытия (PCOV — построчно) объединил FK-гарантированные `?? throw` (запись существующего комментария) в одно условие `if ($post === null || ...)`, чтобы не было недостижимой строки; добавил тесты на достижимые ветки (404 на отсутствующие сущности, 422 на несуществующее упоминание в комментарии, пустая лента).

## Интеграционные правки по итогам финального прогона
- `openapi:generate` падал на рекурсивном ресурсе: `PostResource` имел `self|null $original` — генератор не понимает `self`. Заменено на явный `PostResource|null` (как в плане), генерация снова работает (31 операция). `public/openapi/openapi.yml` перегенерирован.
- `ConfigShapeTest`: добавлен раздел `user` в ожидаемый список (новый `app/config/user.php`).
- Регистрация 7 видов уведомлений Posts увеличила глобальную матрицу настроек Notifications — обновлены тесты `NotificationUseCaseTest`/`NotificationHttpTest` (фильтрация по фикстурному виду вместо предположения о единственном виде).

## Изменения в docs
- `docs/rules.md`: добавлено правило «Не подменять методы коллекции array-функциями» (по замечанию пользователя про `array_filter($collection->all(), ...)` вместо `$collection->filter(...)`).

## Финальная проверка
- `make qa` (стиль + `make phpstan` + покрытие) — зелёный. `make phpstan` — без ошибок. Тесты: 1103 зелёных. Покрытие всего проекта — 100.00% (порог 100%). 1 PHPUnit Notice — предсуществующий, не связан с изменениями.

## Заметки

- Решение по «7 классов *NotificationType»: реализовано как один enum `PostNotificationType` (вариант B из README Notifications) — DRY, исчерпывающий `match`, регистрация `...cases()`. Контракт (7 зарегистрированных видов) соблюдён.
- Конфликт правил vs план в разрешении аватара: план предлагал `Media GetMediaUrl` (бросает на отсутствие/не-Ready), но try-catch в Handler запрещён. Добавлен не бросающий `Media/Application/Query/FindMediaUrl` (возвращает `MediaUrlResult|null`); поведение профиля по плану («медиа недоступно → дефолтный аватар») сохранено без try-catch. `UserPublicProfileAssembler` вызывает его напрямую (шина теряет nullable в выводе типов PHPStan).
- Фаза 1+2: `make phpstan` зелёный; новые тесты (31) зелёные.

## Изменения в docs
