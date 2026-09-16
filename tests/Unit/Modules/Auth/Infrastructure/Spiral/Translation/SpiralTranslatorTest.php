<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Spiral\Translation;

use App\Modules\Auth\Infrastructure\Spiral\Translation\SpiralTranslator;
use PHPUnit\Framework\TestCase;
use Spiral\Translator\TranslatorInterface;

final class SpiralTranslatorTest extends TestCase
{
    public function testDelegatesToSpiralTranslatorWithSameArguments(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('trans')
            ->with(
                id: 'app.auth.code_email_subject',
                parameters: ['code' => '123456'],
                domain: 'auth',
                locale: 'ru',
            )
            ->willReturn('Код для входа в YogaLoka');

        $spiralTranslator = new SpiralTranslator(translator: $translator);

        $translated = $spiralTranslator->trans(
            id: 'app.auth.code_email_subject',
            parameters: ['code' => '123456'],
            domain: 'auth',
            locale: 'ru',
        );

        self::assertSame('Код для входа в YogaLoka', $translated);
    }
}
