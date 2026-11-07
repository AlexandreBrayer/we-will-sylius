<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Documentation\Application\DescribeProductDocumentation;

/**
 * @implements ProviderInterface<DocumentationCoverageView>
 */
final readonly class DocumentationCoverageProvider implements ProviderInterface
{
    public function __construct(private DescribeProductDocumentation $describeProductDocumentation)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DocumentationCoverageView
    {
        $code = $uriVariables['productCode'] ?? '';

        return DocumentationCoverageView::fromDocumentation(($this->describeProductDocumentation)(is_scalar($code) ? (string) $code : ''));
    }
}
