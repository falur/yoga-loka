<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\DetachDeletedMedia;

use App\Modules\Posts\Application\Contract\DetachMediaAttachmentsContract;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Реакция Posts на MediaDeletedEvent модуля Media: снимает все вложения, указывающие на удалённое
 * медиа. Идемпотентен естественно, без отдельного журнала доставок — DELETE по mediaId находит уже
 * пустой набор строк при повторной доставке того же события и ничего не меняет (см. докблок
 * DetachMediaAttachmentsContract), поэтому проверка по идентификатору outbox-события не нужна.
 */
final readonly class DetachDeletedMediaHandler
{
    public function __construct(
        private DetachMediaAttachmentsContract $detachMediaAttachments,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(DetachDeletedMediaCommand $command): void
    {
        $detachedCount = $this->detachMediaAttachments->detachByMediaId(
            PostMediaReference::fromString($command->mediaId),
        );

        $this->logger->debug(message: 'Вложения удалённого медиа сняты.', context: [
            'mediaId' => $command->mediaId,
            'detachedCount' => $detachedCount,
        ]);
    }
}
