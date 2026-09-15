<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Bootloader;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Auth\Infrastructure\Spiral\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Spiral\Auth\UserActorProvider;
use App\Modules\Auth\Infrastructure\Spiral\Hash\HmacSecretHasher;
use App\Modules\Auth\Infrastructure\Spiral\Mail\SpiralLoginCodeMailer;
use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use Spiral\Auth\Transport\HeaderTransport;
use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader;
use Spiral\Bootloader\Auth\HttpAuthBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Views\Bootloader\ViewsBootloader;

/**
 * Каркас аутентификации модуля Auth. Контракты Application привязаны к инфраструктурным
 * реализациям. Транспорт (Authorization: Bearer), хранилище токенов (cycle) и actor-provider
 * регистрируются кодом без app/config/auth.php. View-шаблоны модуля (например письмо с кодом
 * входа) лежат в Infrastructure/Spiral/Resources/views и регистрируются под namespace `auth`. Пара
 * LoginCodeRequested → SendLoginCodeJob регистрируется в outbox-реестре.
 */
final class AuthBootloader extends Bootloader
{
    /**
     * Namespace представлений модуля Auth (ссылка на шаблон: `auth:<имя>`).
     */
    public const string VIEW_NAMESPACE = 'auth';

    protected const BINDINGS = [
        SecretHasherContract::class => HmacSecretHasher::class,
        TokenGeneratorContract::class => RandomTokenGenerator::class,
        AuthTokenStorageContract::class => CycleTokenStorage::class,
        LoginCodeMailerContract::class => SpiralLoginCodeMailer::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function defineDependencies(): array
    {
        return [HttpAuthBootloader::class, SpiralAuthBootloader::class, ViewsBootloader::class];
    }

    public function init(HttpAuthBootloader $httpAuth, SpiralAuthBootloader $auth, ViewsBootloader $views): void
    {
        $views->addDirectory(
            namespace: self::VIEW_NAMESPACE,
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/views', \dirname(path: __DIR__, levels: 3)),
        );
        $httpAuth->addTransport(
            name: 'header',
            transport: new HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s'),
        );
        $httpAuth->addTokenStorage(name: 'cycle', storage: CycleTokenStorage::class);
        $auth->addActorProvider(UserActorProvider::class);
    }

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: LoginCodeRequested::class,
            outboxJobClass: SendLoginCodeJob::class,
        );
    }
}
