<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tags\Application;

use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsCommand;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsHandler;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Domain\ValueObject\TagId;
use Tests\Feature\Modules\Tags\TagsApplicationTestCase;

final class ResolveTagsHandlerTest extends TagsApplicationTestCase
{
    public function testCreatesNewTags(): void
    {
        $user = $this->persistUser();

        $result = $this->handler()->handle(new ResolveTagsCommand(
            texts: ['yoga', 'meditation'],
            creatorUserId: $user->id->value(),
        ));

        self::assertCount(2, $result->tagIds);
        self::assertNotNull($this->tagRepository()->findByText(TagText::fromString('yoga')));
        self::assertNotNull($this->tagRepository()->findByText(TagText::fromString('meditation')));
    }

    public function testReusesExistingTagWithStableId(): void
    {
        $user = $this->persistUser();
        $existing = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
        $this->persist($existing);

        $result = $this->handler()->handle(new ResolveTagsCommand(
            texts: ['yoga'],
            creatorUserId: $user->id->value(),
        ));

        self::assertSame([$existing->id->value()], $result->tagIds);
    }

    public function testDeduplicatesRepeatedTextInSingleRequest(): void
    {
        $user = $this->persistUser();

        $result = $this->handler()->handle(new ResolveTagsCommand(
            texts: ['yoga', 'Yoga', 'yoga'],
            creatorUserId: $user->id->value(),
        ));

        self::assertCount(1, $result->tagIds);
        self::assertCount(1, $this->tagRepository()->findByTexts(TagText::fromString('yoga')));
    }

    public function testPreservesInputTextOrder(): void
    {
        $user = $this->persistUser();

        $result = $this->handler()->handle(new ResolveTagsCommand(
            texts: ['beta', 'alpha'],
            creatorUserId: $user->id->value(),
        ));

        self::assertCount(2, $result->tagIds);

        $firstTag = $this->tagRepository()->findById(TagId::fromString($result->tagIds[0]));
        $secondTag = $this->tagRepository()->findById(TagId::fromString($result->tagIds[1]));

        self::assertNotNull($firstTag);
        self::assertNotNull($secondTag);
        self::assertSame('beta', $firstTag->text->value());
        self::assertSame('alpha', $secondTag->text->value());
    }

    public function testEmptyInputReturnsEmptyResult(): void
    {
        $user = $this->persistUser();

        $result = $this->handler()->handle(new ResolveTagsCommand(
            texts: [],
            creatorUserId: $user->id->value(),
        ));

        self::assertSame([], $result->tagIds);
    }

    private function handler(): ResolveTagsHandler
    {
        return $this->getContainer()->get(ResolveTagsHandler::class);
    }
}
