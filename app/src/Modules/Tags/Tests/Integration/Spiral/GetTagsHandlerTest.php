<?php

declare(strict_types=1);

namespace App\Modules\Tags\Tests\Integration\Spiral;

use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Domain\ValueObject\TagId;

final class GetTagsHandlerTest extends TagsApplicationTestCase
{
    public function testReturnsIdToTextMap(): void
    {
        $user = $this->persistUser();
        $yoga = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
        $meditation = Tag::create(text: TagText::fromString('meditation'), createdBy: $user->id);
        $this->persistTag($yoga);
        $this->persistTag($meditation);

        $tags = $this->handler()->handle(new GetTagsQuery(
            tagIds: [$yoga->id->value(), $meditation->id->value()],
        ));

        // Сравниваем карту id->text с учётом ключей, но без зависимости от порядка строк БД:
        // findByIds() не задаёт ORDER BY, поэтому сортируем обе карты по ключу и сравниваем точно.
        $expected = [
            $yoga->id->value() => 'yoga',
            $meditation->id->value() => 'meditation',
        ];
        $actual = $tags->all();
        \ksort($expected);
        \ksort($actual);
        self::assertSame($expected, $actual);
    }

    public function testExcludesMissingIds(): void
    {
        $user = $this->persistUser();
        $yoga = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
        $this->persistTag($yoga);

        $tags = $this->handler()->handle(new GetTagsQuery(
            tagIds: [$yoga->id->value(), TagId::generate()->value()],
        ));

        self::assertCount(1, $tags);
        self::assertSame('yoga', $tags->get($yoga->id->value()));
    }

    public function testEmptyInputReturnsEmptyCollection(): void
    {
        $tags = $this->handler()->handle(new GetTagsQuery(tagIds: []));

        self::assertCount(0, $tags);
    }

    private function handler(): GetTagsHandler
    {
        return $this->getContainer()->get(GetTagsHandler::class);
    }
}
