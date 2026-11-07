<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Domain;

use App\Yack\Documentation\Domain\Notice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Notice::class)]
final class NoticeTest extends TestCase
{
    public function testCoversItsProductsWhateverTheCase(): void
    {
        $notice = new Notice('Notice Aquaflow (FICTIF)', 'Aquaflow 200 L', ['YK-ECS-T200', 'yk-ecs-t270'], null, []);

        $this->assertTrue($notice->covers('yk-ecs-t200'));
        $this->assertTrue($notice->covers('YK-ECS-T270 '));
        $this->assertFalse($notice->covers('YK-ECS-E200'));
    }

    public function testTitleIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Notice(' ', 'Aquaflow', [], null, []);
    }
}
