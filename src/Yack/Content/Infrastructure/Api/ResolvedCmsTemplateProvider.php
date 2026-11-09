<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Yack\Content\Application\CmsTemplateResolver;
use App\Yack\Content\Domain\CmsTemplateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ResolvedCmsTemplateView>
 */
final readonly class ResolvedCmsTemplateProvider implements ProviderInterface
{
    public function __construct(
        private CmsTemplateResolver $resolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ResolvedCmsTemplateView
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;
        $type = CmsTemplateType::tryFrom((string) $query?->get('type'))
            ?? throw new BadRequestHttpException('Paramètre « type » attendu : product ou taxon.');
        $code = trim((string) $query?->get('code'));
        if ('' === $code) {
            throw new BadRequestHttpException('Paramètre « code » attendu : code du produit ou de la catégorie.');
        }

        if (CmsTemplateType::Product === $type) {
            $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $code])
                ?? throw new NotFoundHttpException(sprintf('Produit « %s » introuvable.', $code));
            $resolved = $this->resolver->forProduct($product);
        } else {
            $taxon = $this->entityManager->getRepository(Taxon::class)->findOneBy(['code' => $code])
                ?? throw new NotFoundHttpException(sprintf('Catégorie « %s » introuvable.', $code));
            $resolved = $this->resolver->forTaxon($taxon);
        }

        if (null === $resolved) {
            throw new NotFoundHttpException('Aucun template publié ne s\'applique (pas de template par défaut).');
        }

        $template = $resolved->template;

        return new ResolvedCmsTemplateView(
            (int) $template->getId(),
            $template->getType()->value,
            $template->getName(),
            $resolved->source->value,
            $resolved->sourceCode,
            $template->getBlocks(),
            $template->getUpdatedAt(),
        );
    }
}
