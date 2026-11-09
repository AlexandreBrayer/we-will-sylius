<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Infrastructure\Ai;

use App\Yack\Common\Infrastructure\Ai\JsonValue;
use PHPUnit\Framework\TestCase;

final class JsonValueTest extends TestCase
{
    public function testAtFollowsKeysAndIndexes(): void
    {
        $body = ['choices' => [['message' => ['content' => '{"ok":true}']]]];

        self::assertSame('{"ok":true}', JsonValue::at($body, 'choices', 0, 'message', 'content'));
    }

    public function testAtGivesNullOnAMissingStepOrAScalar(): void
    {
        self::assertNull(JsonValue::at(['choices' => []], 'choices', 0, 'message'));
        self::assertNull(JsonValue::at('texte', 'choices'));
        self::assertNull(JsonValue::at(null, 'choices'));
    }

    public function testObjectKeepsNamedKeysOnly(): void
    {
        self::assertSame(['brand' => 'MHI'], JsonValue::object(['brand' => 'MHI', 0 => 'liste']));
        self::assertSame([], JsonValue::object([]));
        self::assertNull(JsonValue::object('texte'));
        self::assertNull(JsonValue::object(null));
    }

    public function testItemsListsAnArrayAndIgnoresAnythingElse(): void
    {
        self::assertSame(['a', 'b'], JsonValue::items([3 => 'a', 7 => 'b']));
        self::assertSame([], JsonValue::items('texte'));
        self::assertSame([], JsonValue::items(null));
    }
}
