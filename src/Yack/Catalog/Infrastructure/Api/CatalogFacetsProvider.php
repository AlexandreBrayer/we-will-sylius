<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Account\Domain\CommercePermission;
use App\Yack\Catalog\Application\ListFacets;
use App\Yack\Catalog\Domain\InvalidCatalogFilter;
use App\Yack\Catalog\Domain\ProductKind;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Facets of a listing; price bounds and price range only for a customer who sees prices
 * (Account rule `CommercePermission::SeePrices`): visitors and PRO customers get `price: null`
 * and their price parameters are ignored.
 *
 * @implements ProviderInterface<CatalogFacetsView>
 */
final readonly class CatalogFacetsProvider implements ProviderInterface
{
    public function __construct(
        private ListFacets $listFacets,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CatalogFacetsView
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query->all() : [];

        $withPrices = $this->authorizationChecker->isGranted(CommercePermission::SeePrices->value);

        try {
            $filter = CatalogFilterQuery::filter($query, $withPrices);
        } catch (InvalidCatalogFilter $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $type = $query[ProductTypeFilterExtension::QUERY_PARAMETER] ?? null;
        $type = is_string($type) && '' !== $type ? $type : null;
        if (null === $type) {
            $facets = ($this->listFacets)(null, $filter, $withPrices);

            return new CatalogFacetsView(null, $facets['facets'], $facets['price']);
        }

        $kind = ProductKind::fromApiType($type);
        if (null === $kind) {
            // Unknown type: the product listing is empty, so are its facets.
            return new CatalogFacetsView($type, [], null);
        }

        $facets = ($this->listFacets)($kind, $filter, $withPrices);

        return new CatalogFacetsView($type, $facets['facets'], $facets['price']);
    }
}
