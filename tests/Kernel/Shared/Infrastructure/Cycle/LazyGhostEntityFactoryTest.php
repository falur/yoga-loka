<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Cycle;

use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\LazyGhostEntityFactory;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Reference\Reference;
use Cycle\ORM\RelationMap;
use Tests\TestCase;

final class LazyGhostEntityFactoryTest extends TestCase
{
    public function testUpgradeResolvesEagerReferenceForInitializedEntity(): void
    {
        $factory = new LazyGhostEntityFactory();
        $relMap = RelationMap::build($this->orm(), 'media');
        $media = $this->media();

        // Не-lazy (инициализированный) объект + ReferenceInterface: ссылка с уже заданным
        // значением резолвится без обращения к БД (HasMany::resolve возвращает hasValue()).
        $reference = new Reference('media', ['media_id' => $media->id->value()]);
        $reference->setValue([]);

        $result = $factory->upgrade(relMap: $relMap, entity: $media, data: ['imageConversions' => $reference]);

        self::assertSame($media, $result);
        self::assertCount(0, $media->imageConversions);
    }

    public function testUpgradeSetsNonReferenceRelationValue(): void
    {
        $factory = new LazyGhostEntityFactory();
        $relMap = RelationMap::build($this->orm(), 'media');
        $media = $this->media();

        $factory->upgrade(
            relMap: $relMap,
            entity: $media,
            data: ['imageConversions' => new MediaImageConversionCollection()],
        );

        self::assertCount(0, $media->imageConversions);
    }

    public function testUpgradeSkipsRelationWithoutReflectedProperty(): void
    {
        $factory = new LazyGhostEntityFactory();
        $relMap = RelationMap::build($this->orm(), 'media');

        $entity = new \stdClass();
        $result = $factory->upgrade(relMap: $relMap, entity: $entity, data: ['imageConversions' => 'whatever']);

        self::assertSame($entity, $result);
    }

    public function testUpgradeSkipsUnknownAndReadonlyProperties(): void
    {
        $factory = new LazyGhostEntityFactory();
        $relMap = RelationMap::build($this->orm(), 'media');

        // Не-связь без отражения на чужой Entity (:93).
        $factory->upgrade(relMap: $relMap, entity: new \stdClass(), data: ['unknownField' => 'x']);

        // Инициализированное readonly-свойство не перезаписывается (:97).
        $readonly = new StubReadonlyTarget();
        $factory->upgrade(relMap: $relMap, entity: $readonly, data: ['locked' => 'new']);

        self::assertSame('init', $readonly->locked);
    }

    public function testExtractRelationsSkipsNonStringRelationName(): void
    {
        $factory = new LazyGhostEntityFactory();

        // Привязка к приватному API Cycle ORM (RelationMap::$innerRelations) — чинить при апгрейде Cycle.
        $relMap = (new \ReflectionClass(RelationMap::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(RelationMap::class, 'innerRelations'))->setValue($relMap, [0 => new \stdClass()]);

        self::assertSame([], $factory->extractRelations(relMap: $relMap, entity: new \stdClass()));
    }

    private function orm(): ORMInterface
    {
        return $this->getContainer()->get(ORMInterface::class);
    }

    private function media(): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }
}

final class StubReadonlyTarget
{
    public function __construct(
        public readonly string $locked = 'init',
    ) {}
}
