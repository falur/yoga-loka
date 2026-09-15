<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\CompleteRegistration;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Repository\RegistrationTicketRepository;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Завершение регистрации по талону. Создание пользователя идёт через публичный контракт User и
 * остаётся вложенным диспатчем (#[Transactional] → SAVEPOINT): занятый ник/email бросает
 * ValidationException 422, откатывая SAVEPOINT и внешнюю транзакцию — поэтому талон НЕ гасится и
 * попытку можно повторить.
 */
final readonly class CompleteRegistrationHandler
{
    public function __construct(
        private RegistrationTicketRepository $registrationTicketRepository,
        private SecretHasherContract $secretHasher,
        private AuthTokenStorageContract $authTokenStorage,
        private UserContract $users,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CompleteRegistrationCommand $command): IssuedTokenPair
    {
        $now = new \DateTimeImmutable();
        $ticketHash = SecretHash::fromString($this->secretHasher->hash($command->ticket));
        $ticket = $this->registrationTicketRepository->findActiveByHashForUpdate($ticketHash)
            ?? throw new AuthenticationException('app.auth.invalid_ticket');

        if ($ticket->isExpired($now)) {
            throw new AuthenticationException('app.auth.invalid_ticket');
        }

        $ticket->consume($now);
        $this->entityManager->persist($ticket);

        $createdUser = $this->users->createUser(
            email: $ticket->email->value(),
            name: $command->name,
            nickname: $command->nickname,
            locale: $command->requestLocale,
        );

        $tokens = $this->authTokenStorage->issuePair(
            userId: UserId::fromString($createdUser->userId),
            device: SessionDevice::fromRequest(ip: $command->ip, userAgent: $command->userAgent),
        );
        $this->entityManager->run();

        $this->logger->info(message: 'Регистрация завершена.', context: [
            'userId' => $createdUser->userId,
            'email' => $ticket->email->value(),
        ]);

        return $tokens;
    }
}
