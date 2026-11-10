<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\FieldChange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FieldChange::class)]
final class FieldChangeTest extends TestCase
{
    public function testItIsStoredAndReadBackAsAnArray(): void
    {
        $change = new FieldChange('stock', 'Stock', '3', '12');

        $this->assertSame(['field' => 'stock', 'label' => 'Stock', 'before' => '3', 'after' => '12'], $change->toArray());
        $this->assertSame($change->toArray(), FieldChange::fromArray($change->toArray())->toArray());
    }

    public function testAStoredValueThatIsNotATextIsReadAsEmpty(): void
    {
        $change = FieldChange::fromArray(['field' => 'stock', 'before' => ['3'], 'after' => 12]);

        $this->assertSame(['field' => 'stock', 'label' => '', 'before' => null, 'after' => '12'], $change->toArray());
    }
}
