<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Catalog\Application\ListFacets;
use App\Yack\Catalog\Domain\InvalidCatalogFilter;
use App\Yack\Catalog\Domain\ProductKind;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<CatalogFacetsView>
 */
final readonly class CatalogFacetsProvider implements ProviderInterface
{
    public function __construct(private ListFacets $listFacets)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CatalogFacetsView
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query->all() : [];

        try {
            $filter = CatalogFilterQuery::filter($query);
        } catch (InvalidCatalogFilter $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $type = $query[ProductTypeFilterExtension::QUERY_PARAMETER] ?? null;
        $type = is_string($type) && '' !== $type ? $type : null;
        if (null === $type) {
            return new CatalogFacetsView(null, ($this->listFacets)(null, $filter));
        }

        $kind = ProductKind::fromApiType($type);

        // Unknown type: the product listing is empty, so are its facets.
        return new CatalogFacetsView($type, null === $kind ? [] : ($this->listFacets)($kind, $filter));
    }
}
