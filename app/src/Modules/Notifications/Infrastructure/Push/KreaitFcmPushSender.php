<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Push;

use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Dto\FcmPushResult;
use App\Modules\Notifications\Application\Dto\NotificationPush;
use App\Modules\Notifications\Application\Exception\FcmPushFailedException;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification;

/**
 * Тонкий адаптер над Kreait\Firebase\Messaging. Шлёт multicast, временный сбой переводит в
 * FcmPushFailedException, а признанные FCM невалидными токены (UNREGISTERED/INVALID_ARGUMENT)
 * возвращает для удаления.
 */
final readonly class KreaitFcmPushSender implements FcmPushSenderContract
{
    public function __construct(
        private Messaging $messaging,
    ) {}

    #[\Override]
    public function send(NotificationPush $push, array $tokens): FcmPushResult
    {
        $message = CloudMessage::new()
            ->withNotification(Notification::create(title: $push->title, body: $push->body))
            ->withData($this->data($push));

        try {
            $report = $this->messaging->sendMulticast(message: $message, registrationTokens: $tokens);
        } catch (MessagingException $exception) {
            throw FcmPushFailedException::transient($exception);
        }

        return new FcmPushResult(invalidTokens: $this->invalidTokens($report));
    }

    /**
     * @return array<non-empty-string, string>
     */
    private function data(NotificationPush $push): array
    {
        $data = [];

        if ($push->action !== null) {
            $data['actionType'] = $push->action->actionType;
            $data['actionId'] = $push->action->actionId;
        }

        if ($push->actor !== null) {
            // FCM data — плоская строковая карта, поэтому снимок автора раскладываем по полям.
            $data['actorId'] = $push->actor->id;
            $data['actorName'] = $push->actor->name;

            // Аватар опционален: нет ссылки -> ключ не кладём (в строковую карту null не поместить).
            if ($push->actor->avatarUrl !== null) {
                $data['actorAvatarUrl'] = $push->actor->avatarUrl;
            }
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function invalidTokens(MulticastSendReport $report): array
    {
        return \array_values(\array_unique([...$report->invalidTokens(), ...$report->unknownTokens()]));
    }
}
