<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\VerifyLoginCode;

use App\Modules\Auth\Application\Command\ResolveLoginCode\LoginCodeOutcome;
use App\Modules\Auth\Application\Command\ResolveLoginCode\ResolveLoginCodeCommand;
use App\Modules\Auth\Application\Command\ResolveLoginCode\ResolveLoginCodeHandler;
use App\Modules\Auth\Domain\Exception\InvalidLoginCodeException;
use App\Modules\Auth\Domain\Exception\SignInNotAllowedException;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\CommandBusInterface;

/**
 * Оркестратор проверки кода БЕЗ #[Transactional]. Делегирует разбор транзакционному
 * ResolveLoginCode (он коммитит consume/attempts++), затем по исходу строит результат или
 * бросает 401 уже после commit — чтобы инкремент попыток пережил отказ.
 */
final readonly class VerifyLoginCodeHandler
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ResolveLoginCodeHandler $resolveLoginCodeHandler,
    ) {}

    #[LogOperation]
    public function handle(VerifyLoginCodeCommand $command): VerifyLoginCodeResult
    {
        $resolution = $this->commandBus->dispatch(
            command: new ResolveLoginCodeCommand(
                email: $command->email,
                code: $command->code,
                ip: $command->ip,
                userAgent: $command->userAgent,
            ),
            handler: $this->resolveLoginCodeHandler->handle(...),
        );

        return match ($resolution->outcome) {
            LoginCodeOutcome::Verified => new VerifyLoginCodeResult(
                needsProfile: false,
                tokens: $resolution->tokens,
                registrationTicket: null,
            ),
            LoginCodeOutcome::NeedsProfile => new VerifyLoginCodeResult(
                needsProfile: true,
                tokens: null,
                registrationTicket: $resolution->registrationTicket,
            ),
            LoginCodeOutcome::NotAllowed => throw new SignInNotAllowedException(),
            LoginCodeOutcome::NoCode,
            LoginCodeOutcome::Expired,
            LoginCodeOutcome::Exhausted,
            LoginCodeOutcome::Wrong => throw new InvalidLoginCodeException(),
        };
    }
}
