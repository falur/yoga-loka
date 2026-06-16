<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\SendLoginCode;

use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Spiral\Translator\TranslatorInterface;

/**
 * Отправка письма с кодом входа. Выполняется в очереди (per-request локали нет), поэтому язык
 * берётся из сообщения с явной передачей в translator и fallback на LocaleConfig.default при
 * неподдерживаемом значении. Тема и тело письма — из переводов домена auth, а сама отправка
 * через framework-mailer инкапсулирована за LoginCodeMailerContract.
 */
final readonly class SendLoginCodeHandler
{
    public function __construct(
        private LoginCodeMailerContract $loginCodeMailer,
        private TranslatorInterface $translator,
        private LocaleConfig $localeConfig,
    ) {}

    #[LogOperation]
    public function handle(SendLoginCodeCommand $command): void
    {
        $locale = $this->resolveLocale($command->locale);

        $this->loginCodeMailer->send(
            email: $command->email,
            subject: $this->translator->trans(
                id: 'app.auth.code_email_subject',
                parameters: [],
                domain: 'auth',
                locale: $locale,
            ),
            body: $this->translator->trans(
                id: 'app.auth.code_email_body',
                parameters: ['code' => $command->code],
                domain: 'auth',
                locale: $locale,
            ),
        );
    }

    private function resolveLocale(string $locale): string
    {
        return \in_array(needle: $locale, haystack: $this->localeConfig->supported, strict: true)
            ? $locale
            : $this->localeConfig->default;
    }
}
