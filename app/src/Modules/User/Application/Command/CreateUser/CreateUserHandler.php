<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\CreateUser;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\Repository\ReservedNicknameRepository;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Modules\User\Domain\Exception\EmailAlreadyTakenException;
use App\Modules\User\Domain\Exception\NicknameAlreadyTakenException;
use App\Shared\Domain\Locale\LocaleResolver;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ReservedNicknameRepository $reservedNicknameRepository,
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
            throw new EmailAlreadyTakenException();
        }

        if (
            $this->userRepository->existsByNickname($nickname)
            || $this->reservedNicknameRepository->isReserved($nickname)
        ) {
            throw new NicknameAlreadyTakenException();
        }

        $user = User::create(
            name: UserName::fromString($command->name),
            email: $email,
            nickname: $nickname,
            locale: $this->resolveLocale($command->locale),
        );
        $user->confirmEmail();

        $this->userRepository->save($user);

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
