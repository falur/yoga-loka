<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\ResolveLoginCode;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

/**
 * Транзакционный разбор кода. Коммитит запись (consume или attempts++) и ВОЗВРАЩАЕТ исход;
 * 401 бросает уже нетранзакционный оркестратор VerifyLoginCode после commit — иначе инкремент
 * попыток откатился бы вместе с throw, и защита от перебора не работала бы.
 */
final readonly class ResolveLoginCodeHandler
{
    private const int TICKET_TTL_SECONDS = 900;

    public function __construct(
        private LoginCodeRepository $loginCodeRepository,
        private SecretHasherContract $secretHasher,
        private TokenGeneratorContract $tokenGenerator,
        private AuthTokenStorageContract $authTokenStorage,
        private UserContract $users,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(ResolveLoginCodeCommand $command): LoginCodeResolution
    {
        $email = EmailAddress::fromString($command->email);
        $now = new \DateTimeImmutable();
        $loginCode = $this->loginCodeRepository->findActiveByEmailForUpdate($email);

        if ($loginCode === null) {
            return LoginCodeResolution::failed(LoginCodeOutcome::NoCode);
        }

        if ($loginCode->isExpired($now)) {
            return LoginCodeResolution::failed(LoginCodeOutcome::Expired);
        }

        if ($loginCode->attemptsExhausted()) {
            return LoginCodeResolution::failed(LoginCodeOutcome::Exhausted);
        }

        if (!$this->secretHasher->verify(secret: $command->code, hash: $loginCode->codeHash->value())) {
            $loginCode->registerFailedAttempt($now);
            $this->entityManager->persist($loginCode);
            $this->entityManager->run();

            return LoginCodeResolution::failed(LoginCodeOutcome::Wrong);
        }

        $loginCode->consume($now);
        $this->entityManager->persist($loginCode);

        return $this->resolveVerifiedCode(
            email: $email,
            now: $now,
            device: SessionDevice::fromRequest(ip: $command->ip, userAgent: $command->userAgent),
        );
    }

    private function resolveVerifiedCode(
        EmailAddress $email,
        \DateTimeImmutable $now,
        SessionDevice $device,
    ): LoginCodeResolution {
        $signIn = $this->users->findForSignIn($email->value());

        if ($signIn === null) {
            return $this->issueRegistrationTicket(email: $email, now: $now);
        }

        if (!$signIn->canSignIn) {
            $this->entityManager->run();

            return LoginCodeResolution::failed(LoginCodeOutcome::NotAllowed);
        }

        $tokens = $this->authTokenStorage->issuePair(
            userId: UserId::fromString($signIn->userId),
            device: $device,
        );
        $this->entityManager->run();

        return LoginCodeResolution::verified($tokens);
    }

    private function issueRegistrationTicket(EmailAddress $email, \DateTimeImmutable $now): LoginCodeResolution
    {
        $ticketRaw = $this->tokenGenerator->generate();
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: $email,
            ticketHash: SecretHash::fromString($this->secretHasher->hash($ticketRaw)),
            expiration: Expiration::after(now: $now, seconds: self::TICKET_TTL_SECONDS),
            now: $now,
        );
        $this->entityManager->persist($ticket);
        $this->entityManager->run();

        return LoginCodeResolution::needsProfile($ticketRaw);
    }
}
