<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaStatus: string
{
    case WaitingUpload = 'waitingUpload';
    case CompletingMultipartUpload = 'completingMultipartUpload';
    case MultipartCompletionFailedCanRetry = 'multipartCompletionFailedCanRetry';
    case MultipartCompletionFailedNeedReupload = 'multipartCompletionFailedNeedReupload';
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case ProcessingFailed = 'processingFailed';
    case Ready = 'ready';
    case ReadyOriginalRemoved = 'readyOriginalRemoved';
}
