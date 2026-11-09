<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Domain;

use App\Yack\Documentation\Domain\Passage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Passage::class)]
final class PassageTest extends TestCase
{
    public function testProductCodesAreNormalisedAndDeduplicated(): void
    {
        $passage = new Passage('Notice Kaze (FICTIF)', 8, 'Longueur de liaison : 20 m.', [' yk-paa-k35', 'YK-PAA-K35', '', 'YK-PAA-K25'], 'Liaisons');

        $this->assertSame(['YK-PAA-K35', 'YK-PAA-K25'], $passage->productCodes);
        $this->assertTrue($passage->covers('yk-paa-k25'));
        $this->assertFalse($passage->covers('YK-PAA-K50'));
    }

    public function testCitationAndIndexedText(): void
    {
        $passage = new Passage('Notice Kaze (FICTIF)', 8, 'Longueur de liaison : 20 m.', [], 'Liaisons');

        $this->assertSame(['source' => 'Notice Kaze (FICTIF)', 'page' => 8], $passage->citation()->toArray());
        $this->assertSame("Liaisons\nLongueur de liaison : 20 m.", $passage->indexedText());
        $this->assertSame('Texte', (new Passage('Notice', 1, 'Texte'))->indexedText());
    }

    public function testEmptyTextIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Passage de documentation vide');

        new Passage('Notice', 1, ' ');
    }

    public function testInvalidPageIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Passage('Notice', 0, 'Texte');
    }
}
