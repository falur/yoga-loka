<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Bootloader;

use GianTiaga\SpiralOutbox\Bootloader\OutboxBootloader;
use GianTiaga\SpiralOutbox\Config\OutboxConfig;
use GianTiaga\SpiralOutbox\Config\OutboxConfigFactory;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Set;

/**
 * Настройка прохода relay для всего приложения.
 *
 * Секцией `outbox` владеет пакет `gian-tiaga/spiral-outbox`, и её разбирает его собственная
 * фабрика — типизированного Config проект на неё не заводит. Приложение задаёт в секции только две
 * вещи: маршруты (их дописывает bootloader каждого модуля-потребителя) и размер пачки прохода.
 * Размер пачки не принадлежит ни одному модулю — один процесс relay обслуживает события всех
 * модулей, — поэтому он объявляется здесь, в общей части Spiral-инфраструктуры, рядом с
 * перечислением имён очередей.
 *
 * Патч выполняется в `boot()`: к этому моменту `init()` отработал у всех bootloader-ов, в том
 * числе у bootloader пакета, который объявляет секцию своим `setDefaults()`.
 */
final class OutboxRelayBootloader extends Bootloader
{
    /**
     * Сколько строк relay берёт за один проход маршрутизации и за один проход отправки. Прежний
     * модуль брал столько же, и одна пачка остаётся заметно меньше секундного потока событий.
     */
    private const int RELAY_BATCH_SIZE = 100;

    /** @return array<int, class-string> */
    public function defineDependencies(): array
    {
        return [OutboxBootloader::class];
    }

    /** @param ConfiguratorInterface<object> $config */
    public function boot(ConfiguratorInterface $config): void
    {
        $config->modify(
            section: OutboxConfig::CONFIG_SECTION,
            patch: new Set(key: OutboxConfigFactory::BATCH_SIZE, value: self::RELAY_BATCH_SIZE),
        );
    }
}
