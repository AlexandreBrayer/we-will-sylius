<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\InvalidCatalogFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InvalidCatalogFilter::class)]
final class InvalidCatalogFilterTest extends TestCase
{
    public function testFieldAndMessage(): void
    {
        $exception = new InvalidCatalogFilter('power_min', 'Puissance minimale invalide.');

        $this->assertSame('power_min', $exception->field);
        $this->assertSame('Puissance minimale invalide.', $exception->getMessage());
        $this->assertInstanceOf(\DomainException::class, $exception);
    }
}
