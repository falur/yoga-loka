<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\RequestLoginCode;

use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Запрос кода входа. Наличие пользователя не раскрываем (в User/Application не ходим, ответ
 * всегда 200). Язык письма берём из локали запроса. Троттлинг: повторный запрос в пределах
 * окна не шлёт новое письмо.
 */
final readonly class RequestLoginCodeHandler
{
    private const int CODE_TTL_SECONDS = 600;
    private const int RESEND_THROTTLE_SECONDS = 60;

    public function __construct(
        private LoginCodeRepository $loginCodeRepository,
        private SecretHasherContract $secretHasher,
        private OutboxEventStoreContract $outboxEventStore,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RequestLoginCodeCommand $command): void
    {
        $email = EmailAddress::fromString($command->email);
        $now = new \DateTimeImmutable();
        $activeCode = $this->loginCodeRepository->findActiveByEmail($email);

        if ($activeCode !== null && $this->isWithinResendWindow(activeCode: $activeCode, now: $now)) {
            $this->logger->debug(message: 'Запрос кода в окне троттлинга, письмо не отправляется.', context: [
                'email' => $email->value(),
            ]);

            return;
        }

        if ($activeCode !== null) {
            $activeCode->consume($now);
            $this->loginCodeRepository->add($activeCode);
        }

        $code = \str_pad(
            string: (string) \random_int(min: 0, max: 999999),
            length: 6,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: $email,
            codeHash: SecretHash::fromString($this->secretHasher->hash($code)),
            expiration: Expiration::after(now: $now, seconds: self::CODE_TTL_SECONDS),
            now: $now,
        );
        $this->outboxEventStore->add(new LoginCodeRequestedEvent(
            email: $email->value(),
            code: $code,
            locale: $command->requestLocale,
        ));
        // Единственный прогон сценария: он уносит в базу погашенный прежний код и сам новый.
        // Событие outbox хранилище пакета вставило выше своим запросом и прогона не ждёт;
        // атомарность даёт общая транзакция сценария, а не общий прогон.
        $this->loginCodeRepository->save($loginCode);

        $this->logger->debug(message: 'Код входа запрошен и поставлен в outbox.', context: [
            'email' => $email->value(),
        ]);
    }

    private function isWithinResendWindow(LoginCode $activeCode, \DateTimeImmutable $now): bool
    {
        $resendAvailableAt = $activeCode->createdAt->add(
            new \DateInterval(\sprintf('PT%dS', self::RESEND_THROTTLE_SECONDS)),
        );

        return $now < $resendAvailableAt;
    }
}
