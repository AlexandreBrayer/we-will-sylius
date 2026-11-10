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
        $exception = new InvalidCatalogFilter('power', 'Tranche de puissance inconnue.');

        $this->assertSame('power', $exception->field);
        $this->assertSame('Tranche de puissance inconnue.', $exception->getMessage());
        $this->assertInstanceOf(\DomainException::class, $exception);
    }
}
