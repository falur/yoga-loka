<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\SendLoginCode;

use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Contract\TranslatorContract;
use App\Shared\Domain\Locale\LocaleResolver;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Отправка письма с кодом входа. Выполняется в очереди (per-request локали нет), поэтому язык
 * берётся из сообщения с явной передачей в translator и сведением к значению по умолчанию при
 * неподдерживаемом значении через LocaleResolver. Тема и тело письма — из переводов домена auth, а
 * сама отправка через framework-mailer инкапсулирована за LoginCodeMailerContract.
 */
final readonly class SendLoginCodeHandler
{
    public function __construct(
        private LoginCodeMailerContract $loginCodeMailer,
        private TranslatorContract $translator,
        private LocaleResolver $localeResolver,
    ) {}

    #[LogOperation]
    public function handle(SendLoginCodeCommand $command): void
    {
        $locale = $this->localeResolver->resolve($command->locale);

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
}
