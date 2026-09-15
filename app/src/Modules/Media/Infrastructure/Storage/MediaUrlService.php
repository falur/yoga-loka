<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Storage;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Shared\Infrastructure\Spiral\Configuration\Media\MediaConfig;
use Illuminate\Support\Collection;

/**
 * Строит URL из УЖЕ загруженного медиа. getUrls — полный набор: оригинал (если не удалён) и все
 * готовые (Ready) конверсии (не-Ready — processing/processingFailed — в набор не попадают). Конверсии
 * берутся из связей сущности (imageConversions/videoConversions/audioConversions),
 * поэтому метод не делает запросов в БД — при условии, что связи загружены eager заранее
 * (репозиторий-метод с ->load(...) или ->load('media.imageConversions') у вызывающего модуля).
 * Если связи не загружены, Cycle подгрузит их лениво — это вернёт N+1, поэтому вызывающий обязан
 * передавать медиа с eager-загруженными конверсиями. Потребитель, которому нужен только оригинал
 * (аватар, лента), берёт из результата original.
 *
 * Срок presigned-ссылки скачивания: значение по умолчанию реализация читает из MediaConfig напрямую
 * (прямая инъекция конфига — класс лежит в Infrastructure), а вызывающий может переопределить его на
 * конкретный вызов. Сервис stateless, поэтому expiresAt всегда считается внутри вызова (не в
 * конструкторе), иначе все наборы URL получили бы один замороженный срок.
 *
 * Недоступность медиа не бросает: не финализированное (ещё не ready и не readyOriginalRemoved) -> null,
 * после удаления оригинала original = null, конверсии резолвятся. Но невалидный переданный TTL
 * (например, явный 0) бросает InvalidDomainValueException — это ошибка входа, а не про доступность медиа.
 */
final readonly class MediaUrlService implements MediaUrlServiceContract
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private MediaConfig $mediaConfig,
    ) {}

    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null
    {
        if (!$media->isFinalized()) {
            return null;
        }

        $resolver = $this->resolverFor(
            visibility: $media->visibility,
            presignedTtlSeconds: $presignedTtlSeconds,
        );

        return new MediaUrlsResult(
            original: $media->isReady() ? $resolver->resolve(storage: $media->storage, path: $media->path) : null,
            conversions: $this->conversionUrls(media: $media, resolver: $resolver),
        );
    }

    /**
     * Резолвер URL по контексту медиа: public — прямые URL без срока (TTL не нужен и не валидируется),
     * private — presigned с единым сроком на весь набор URL одного вызова. Ветвление — исчерпывающий
     * match по MediaVisibility без default: новый вариант видимости не уйдёт молча в приватную ветку,
     * а будет пойман статическим анализом.
     */
    private function resolverFor(MediaVisibility $visibility, int|null $presignedTtlSeconds): MediaUrlResolver
    {
        return match ($visibility) {
            MediaVisibility::Public => new PublicMediaUrlResolver($this->mediaFileService),
            MediaVisibility::Private => $this->presignedResolverFor($presignedTtlSeconds),
        };
    }

    /**
     * Presigned-резолвер с единым сроком на весь набор URL одного вызова. Переопределение TTL строго
     * по `?? `: null -> значение по умолчанию из конфига; явный 0 (или иное значение `< 1`) ->
     * исключение MediaPresignedTtl (нельзя писать `?:`, иначе явный 0 тихо ушёл бы в значение по
     * умолчанию).
     */
    private function presignedResolverFor(int|null $presignedTtlSeconds): PresignedMediaUrlResolver
    {
        $ttl = MediaPresignedTtl::fromInt($presignedTtlSeconds ?? $this->mediaConfig->presignedTtlSeconds);
        $expiresAt = new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $ttl->value())));

        return new PresignedMediaUrlResolver(mediaFileService: $this->mediaFileService, expiresAt: $expiresAt);
    }

    private function conversionUrls(Media $media, MediaUrlResolver $resolver): MediaConversionUrlCollection
    {
        $imageUrls = $this->conversionUrlsOf(
            conversions: $media->imageConversions->toBase(),
            kind: MediaConversionKind::Image,
            resolver: $resolver,
        );
        $videoUrls = $this->conversionUrlsOf(
            conversions: $media->videoConversions->toBase(),
            kind: MediaConversionKind::Video,
            resolver: $resolver,
        );
        $audioUrls = $this->conversionUrlsOf(
            conversions: $media->audioConversions->toBase(),
            kind: MediaConversionKind::Audio,
            resolver: $resolver,
        );

        return new MediaConversionUrlCollection($imageUrls->concat($videoUrls)->concat($audioUrls));
    }

    /**
     * Строит ссылки конверсий одного вида. Обвязка filter+map одинакова для image/video/audio и
     * различается только видом и типом элемента, поэтому вынесена сюда (раньше — три почти одинаковых
     * map-блока). Не-Ready конверсии (processing/processingFailed) отсеиваются: URL отдаётся только для
     * готовых, чтобы потребитель не получил ссылку на ещё не готовый объект.
     *
     * После filter ключи переиндексируются через ->values(): если отсеялась не последняя конверсия,
     * остаток сохранил бы исходные ключи с разрывом, и итоговый набор сериализовался бы как JSON-объект
     * (`{"1": …}`), а не как массив. Переиндексация держит коллекцию списком с ключами от нуля.
     *
     * @param Collection<int, MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     * @return Collection<int, MediaConversionUrl>
     */
    private function conversionUrlsOf(
        Collection $conversions,
        MediaConversionKind $kind,
        MediaUrlResolver $resolver,
    ): Collection {
        return $conversions
            ->filter(
                static fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): bool
                    => $conversion->status === MediaConversionStatus::Ready,
            )
            ->map(
                fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): MediaConversionUrl
                    => $this->conversionUrl(
                        kind: $kind,
                        type: $conversion->type,
                        storage: $conversion->storage,
                        path: $conversion->path,
                        resolver: $resolver,
                    ),
            )
            ->values();
    }

    private function conversionUrl(
        MediaConversionKind $kind,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
        MediaStorage $storage,
        MediaPath $path,
        MediaUrlResolver $resolver,
    ): MediaConversionUrl {
        $resolved = $resolver->resolve(storage: $storage, path: $path);

        return new MediaConversionUrl(kind: $kind, type: $type, url: $resolved->url, expiresAt: $resolved->expiresAt);
    }
}
