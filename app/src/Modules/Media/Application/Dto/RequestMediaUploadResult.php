<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * Результат RequestMediaUpload. Для single заполнен putUrl; для multipart — uploadId и parts.
 * Наружу отдаётся не доменная Entity.
 */
final readonly class RequestMediaUploadResult
{
    public function __construct(
        public string $mediaId,
        public MediaUploadMode $uploadMode,
        public \DateTimeImmutable $expiresAt,
        public string|null $putUrl,
        public string|null $uploadId,
        public MediaPresignedPartCollection|null $parts,
    ) {}

    public static function single(string $mediaId, string $putUrl, \DateTimeImmutable $expiresAt): self
    {
        return new self(
            mediaId: $mediaId,
            uploadMode: MediaUploadMode::Single,
            expiresAt: $expiresAt,
            putUrl: $putUrl,
            uploadId: null,
            parts: null,
        );
    }

    public static function multipart(
        string $mediaId,
        string $uploadId,
        MediaPresignedPartCollection $parts,
        \DateTimeImmutable $expiresAt,
    ): self {
        return new self(
            mediaId: $mediaId,
            uploadMode: MediaUploadMode::Multipart,
            expiresAt: $expiresAt,
            putUrl: null,
            uploadId: $uploadId,
            parts: $parts,
        );
    }
}
