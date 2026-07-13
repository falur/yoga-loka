+ `app/src/Modules/Media/Application/Service/MediaConversionsChecker.php` и `app/src/Modules/Media/Domain/Enum/MediaConversionKind.php`  
добавить в ревью как обязательный риск: `git status` показывает их untracked, а код уже ссылается на эти классы. Если их не добавить в коммит, приложение сломается на автозагрузке.

− пункт 1: убрать утверждение, что полный `FindMediaUrl` «нигде не помечен как осознанная заготовка». Это неверно: `app/src/Modules/Media/README.md` и `docs/arch.md` уже описывают `FindMediaUrl`/`getUrls` как публичный API foundational-модуля.

~ пункт 1: переформулировать мягче: «полный путь сейчас не имеет runtime-потребителя, но уже задокументирован как публичный API Media; риск только в поддержке неиспользуемой поверхности».

~ пункт 2: замечание про N+1 верное, но фразу «изменение удешевляет, убрав загрузку конверсий» надо убрать или уточнить. В diff относительно `HEAD` старый профильный путь тоже ходил через `findById` без загрузки конверсий; регресса по числу запросов нет, но и улучшения по конверсиям относительно `HEAD` тоже нет.

? `make test` и `make phpstan` не запускал; фактическое число SQL-запросов для N+1 не измерял, вывод сделан статически по diff и вызовам `GetUserPublicProfilesHandler -> UserPublicProfileAssembler -> FindMediaOriginalUrlHandler`.
