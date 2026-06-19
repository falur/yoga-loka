<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tags\Application;

use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\ValueObject\TagId;
use Tests\Feature\Modules\Tags\TagsApplicationTestCase;

final class GetTagsHandlerTest extends TagsApplicationTestCase
{
    public function testReturnsIdToTextMap(): void
    {
        $user = $this->persistUser();
        $yoga = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
        $meditation = Tag::create(text: TagText::fromString('meditation'), createdBy: $user->id);
        $this->persist($yoga);
        $this->persist($meditation);

        $tags = $this->handler()->handle(new GetTagsQuery(
            tagIds: [$yoga->id->value(), $meditation->id->value()],
        ));

        self::assertSame('yoga', $tags->get($yoga->id->value()));
        self::assertSame('meditation', $tags->get($meditation->id->value()));
        self::assertCount(2, $tags);
    }

    public function testExcludesMissingIds(): void
    {
        $user = $this->persistUser();
        $yoga = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
        $this->persist($yoga);

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
