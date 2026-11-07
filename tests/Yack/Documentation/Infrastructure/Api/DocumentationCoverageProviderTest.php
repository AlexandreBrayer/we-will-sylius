<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use App\Tests\Yack\Documentation\Application\InMemoryDocumentationCorpus;
use App\Yack\Documentation\Application\DescribeProductDocumentation;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Infrastructure\Api\DocumentationCoverageProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Needs API Platform (vendor/): run on the Mac only.
 *
 * @internal
 */
#[CoversClass(DocumentationCoverageProvider::class)]
final class DocumentationCoverageProviderTest extends TestCase
{
    public function testCoverageOfTheProductOfTheUrl(): void
    {
        $provider = new DocumentationCoverageProvider(new DescribeProductDocumentation(new InMemoryDocumentationCorpus([
            new Notice('Notice Evolys 8 kW (FICTIF)', 'Evolys 8 kW', ['YK-PAE-M08'], 'Longueur ?', []),
        ])));

        $view = $provider->provide(new Get(), ['productCode' => 'YK-PAE-M08']);
        $this->assertSame(['Notice Evolys 8 kW (FICTIF)'], $view->notices);

        $none = $provider->provide(new Get(), ['productCode' => ['tableau']]);
        $this->assertSame('', $none->productCode);
        $this->assertSame([], $none->notices);
    }
}
