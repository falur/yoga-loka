<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Serializer;

use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Exception\OutboxMessageSerializationException;
use App\Modules\Outbox\Application\Contract\SerializedOutboxMessage;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\Normalizer\Normalizer;
use CuyZ\Valinor\NormalizerBuilder;

final readonly class ValinorOutboxMessageSerializer implements OutboxMessageSerializerContract
{
    private TreeMapper $mapper;

    /**
     * @var Normalizer<string>
     */
    private Normalizer $normalizer;

    public function __construct()
    {
        $this->mapper = (new MapperBuilder())
            ->allowPermissiveTypes()
            ->allowScalarValueCasting()
            ->mapper();
        $this->normalizer = (new NormalizerBuilder())->normalizer(Format::json());
    }

    #[\Override]
    public function serialize(IntegrationEvent $integrationEvent): SerializedOutboxMessage
    {
        return new SerializedOutboxMessage(
            type: $integrationEvent::class,
            payload: $this->normalizer->normalize($integrationEvent),
        );
    }

    #[\Override]
    public function deserialize(SerializedOutboxMessage $serializedOutboxMessage): IntegrationEvent
    {
        if (!\is_subclass_of(object_or_class: $serializedOutboxMessage->type, class: IntegrationEvent::class)) {
            throw OutboxMessageSerializationException::unsupportedMessageType($serializedOutboxMessage->type);
        }

        return $this->mapper->map(
            signature: $serializedOutboxMessage->type,
            source: Source::json($serializedOutboxMessage->payload),
        );
    }
}
