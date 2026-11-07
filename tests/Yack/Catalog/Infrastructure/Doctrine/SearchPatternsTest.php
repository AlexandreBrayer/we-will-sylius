<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Doctrine;

use App\Yack\Catalog\Infrastructure\Doctrine\SearchPatterns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchPatterns::class)]
final class SearchPatternsTest extends TestCase
{
    public function testWildcardsTypedByTheCustomerAreMatchedAsSuch(): void
    {
        $this->assertSame('100\\% r32\\_x\\\\y', SearchPatterns::escape('100% R32_X\\Y'));
        $this->assertSame('%srk25%', SearchPatterns::contains('SRK25'));
        $this->assertSame('srk25%', SearchPatterns::startsWith('SRK25'));
    }

    public function testAWordAlsoMatchesItsSynonyms(): void
    {
        $this->assertSame(['%pac%', '%pompe à chaleur%', '%pompes à chaleur%'], SearchPatterns::alternatives('PAC'));
        $this->assertSame(['%evolys%'], SearchPatterns::alternatives('Evolys'));
    }
}
