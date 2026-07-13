<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\CreateUser;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Locale\LocaleResolver;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ReservedNicknameRepository $reservedNicknameRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private LocaleResolver $localeResolver,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CreateUserCommand $command): CreateUserResult
    {
        $email = Email::fromString($command->email);
        $nickname = UserNickname::fromString($command->nickname);

        if ($this->userRepository->existsByEmail($email)) {
            throw new ValidationException('app.user.email_taken');
        }

        if (
            $this->userRepository->existsByNickname($nickname)
            || $this->reservedNicknameRepository->isReserved($nickname)
        ) {
            throw new ValidationException('app.user.nickname_taken');
        }

        $user = User::create(
            name: UserName::fromString($command->name),
            email: $email,
            nickname: $nickname,
            locale: $this->resolveLocale($command->locale),
        );
        $user->confirmEmail();

        $this->entityManager->persist($user);
        $this->entityManager->run();

        $this->logger->debug(message: 'Пользователь создан.', context: [
            'userId' => $user->id->value(),
            'email' => $email->value(),
        ]);

        return new CreateUserResult(userId: $user->id->value());
    }

    /**
     * Нормализует локаль запроса: неподдерживаемое значение сводится к значению по умолчанию через
     * LocaleResolver, чтобы вход вне HTTP-потока (консоль, очередь) не приводил к 500 из-за Locale::from().
     */
    private function resolveLocale(string $locale): Locale
    {
        return Locale::from($this->localeResolver->resolve($locale));
    }
}
