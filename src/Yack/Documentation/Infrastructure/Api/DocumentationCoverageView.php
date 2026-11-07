<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Yack\Documentation\Application\ProductDocumentation;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/v2/shop/yack/documentation-coverages/{productCode}: notices of a product and the sample
 * question of its page (logged-in customers, 401 for a visitor). Always 200: `notices` is empty
 * and `exampleQuestion` null when the product has no notice.
 */
#[ApiResource(
    shortName: 'YackDocumentationCoverage',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/documentation-coverages/{productCode}',
            requirements: ['productCode' => '[A-Za-z0-9][A-Za-z0-9-]{0,63}'],
            normalizationContext: ['groups' => ['documentation_coverage:list', 'documentation_coverage:item']],
            security: "is_granted('ROLE_USER')",
            provider: DocumentationCoverageProvider::class,
            name: 'app_yack_api_shop_documentation_coverage_get',
        ),
    ],
)]
final readonly class DocumentationCoverageView
{
    /** @param list<string> $notices titles of the notices (the sources cited by the answers) */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['documentation_coverage:list'])]
        public string $productCode,
        #[Groups(['documentation_coverage:list'])]
        public array $notices,
        #[Groups(['documentation_coverage:item'])]
        public ?string $exampleQuestion,
    ) {
    }

    public static function fromDocumentation(ProductDocumentation $documentation): self
    {
        return new self($documentation->productCode, $documentation->notices, $documentation->exampleQuestion);
    }
}
