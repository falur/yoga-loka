<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Bootloader;

use Doctrine\Common\Annotations\AnnotationReader;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Cycle\ORM\Select::__clone() несёт в докблоке тег @attention — это просто пометка для
 * человека, а не аннотация. Наш App\Shared\Infrastructure\Persistence\Cycle\WhenSelect наследует этот
 * метод, поэтому при сканировании классов Doctrine-ридер аннотаций (через spiral/attributes)
 * принимает @attention за аннотацию и падает с SemanticAttributeException.
 *
 * Помечаем тег как игнорируемый текст — ровно тем же механизмом, которым spiral/attributes
 * уже глушит mixin/yield/note/type. Регистрируем до сканирования токенайзера.
 */
final class AnnotationsBootloader extends Bootloader
{
    public function init(): void
    {
        AnnotationReader::addGlobalIgnoredName('attention');
    }
}
