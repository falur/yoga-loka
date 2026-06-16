<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework;

use Spiral\Boot\Bootloader\CoreBootloader;
use Spiral\Bootloader as Framework;
use Spiral\Bootloader\Http\HttpBootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Bootloader\Views\TranslatedCacheBootloader;
use Spiral\Cache\Bootloader\CacheBootloader;
use Spiral\Cycle\Bootloader as CycleBridge;
use Spiral\DataGrid\Bootloader\GridBootloader;
use Spiral\Debug\Bootloader\DumperBootloader;
use Spiral\Distribution\Bootloader\DistributionBootloader;
use Spiral\DotEnv\Bootloader\DotenvBootloader;
use Spiral\Events\Bootloader\EventsBootloader;
use Spiral\League\Event\Bootloader\EventBootloader;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\Nyholm\Bootloader\NyholmBootloader;
use Spiral\Prototype\Bootloader\PrototypeBootloader;
use Spiral\Queue\Bootloader\QueueBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Spiral\Scaffolder\Bootloader\ScaffolderBootloader;
use Spiral\Scheduler\Bootloader\SchedulerBootloader;
use Spiral\SendIt\Bootloader\MailerBootloader;
use Spiral\Sentry\Bootloader\SentryReporterBootloader;
use Spiral\Storage\Bootloader\StorageBootloader;
use Spiral\TemporalBridge\Bootloader as TemporalBridge;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
use Spiral\Twig\Bootloader\TwigBootloader;
use Spiral\Validation\Bootloader\ValidationBootloader;
use Spiral\Validation\Symfony\Bootloader\ValidatorBootloader;
use Spiral\Views\Bootloader\ViewsBootloader;
use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
use App\Modules\Notifications\Infrastructure\Bootloader\NotificationsBootloader;
use App\Modules\Outbox\Infrastructure\Bootloader\OutboxBootloader;
use App\Modules\Outbox\Infrastructure\Bootloader\OutboxConsoleBootloader;
use GianTiaga\SpiralApiErrors\Bootloader\ApiErrorBootloader;
use GianTiaga\SpiralCqrs\Bootloader\CqrsBootloader;
use GianTiaga\SpiralOpenApi\Bootloader\OpenApiToolsBootloader;

class Kernel extends \Spiral\Framework\Kernel
{
    #[\Override]
    public function defineSystemBootloaders(): array
    {
        return [
            CoreBootloader::class,
            DotenvBootloader::class,
            TokenizerListenerBootloader::class,

            DumperBootloader::class,
        ];
    }

    #[\Override]
    public function defineBootloaders(): array
    {
        return [
            // Логирование и обработка исключений
            MonologBootloader::class,
            Bootloader\ExceptionHandlerBootloader::class,

            // Логи приложения
            Bootloader\ConfigBootloader::class,
            Bootloader\LoggingBootloader::class,

            // RoadRunner
            RoadRunnerBridge\LoggerBootloader::class,
            RoadRunnerBridge\QueueBootloader::class,
            RoadRunnerBridge\HttpBootloader::class,
            RoadRunnerBridge\CacheBootloader::class,
            RoadRunnerBridge\LockBootloader::class,

            // Базовые сервисы
            Framework\SnapshotsBootloader::class,

            // Безопасность и валидация
            Framework\Security\EncrypterBootloader::class,
            Framework\Security\FiltersBootloader::class,
            Framework\Security\GuardBootloader::class,

            // HTTP-расширения
            HttpBootloader::class,
            Framework\Http\ErrorHandlerBootloader::class,
            Framework\Http\RouterBootloader::class,
            Framework\Http\JsonPayloadsBootloader::class,
            Framework\Http\CookiesBootloader::class,
            Framework\Http\SessionBootloader::class,
            Framework\Http\CsrfBootloader::class,
            Framework\Http\PaginationBootloader::class,

            // Базы данных
            CycleBridge\DatabaseBootloader::class,
            CycleBridge\MigrationsBootloader::class,

            // ORM
            CycleBridge\SchemaBootloader::class,
            CycleBridge\CycleOrmBootloader::class,
            CycleBridge\AnnotatedBootloader::class,

            // Диспетчер событий
            EventsBootloader::class,
            EventBootloader::class,

            // Планировщик
            SchedulerBootloader::class,

            // Sentry и сборщики данных
            SentryReporterBootloader::class,
            Framework\DebugBootloader::class,
            Framework\Debug\LogCollectorBootloader::class,
            Framework\Debug\HttpCollectorBootloader::class,

            // Представления
            ViewsBootloader::class,
            TwigBootloader::class,

            // Очереди
            QueueBootloader::class,

            // Кэш
            CacheBootloader::class,

            // Хранилище файлов
            StorageBootloader::class,
            DistributionBootloader::class,

            // Интернационализация
            I18nBootloader::class,
            TranslatedCacheBootloader::class,
            OpenApiToolsBootloader::class,
            CqrsBootloader::class,
            OutboxBootloader::class,
            MediaBootloader::class,
            NotificationsBootloader::class,

            // Почта
            MailerBootloader::class,

            // Data Grid
            GridBootloader::class,

            // Temporal
            TemporalBridge\PrototypeBootloader::class,
            TemporalBridge\TemporalBridgeBootloader::class,

            NyholmBootloader::class,

            CycleBridge\DataGridBootloader::class,

            ValidationBootloader::class,
            ValidatorBootloader::class,

            RoadRunnerBridge\MetricsBootloader::class,

            // Консольные команды
            Framework\CommandBootloader::class,
            Bootloader\OpenApiBootloader::class,
            OutboxConsoleBootloader::class,
            RoadRunnerBridge\CommandBootloader::class,
            CycleBridge\CommandBootloader::class,
            ScaffolderBootloader::class,
            RoadRunnerBridge\ScaffolderBootloader::class,
            CycleBridge\ScaffolderBootloader::class,

            // Быстрое прототипирование кода
            PrototypeBootloader::class,

            // Группы маршрутов и middleware
            ApiErrorBootloader::class,
            Bootloader\RoutesBootloader::class,
        ];
    }

    #[\Override]
    public function defineAppBootloaders(): array
    {
        return [
            // Доменный обработчик приложения
            Bootloader\AppBootloader::class,
        ];
    }
}
