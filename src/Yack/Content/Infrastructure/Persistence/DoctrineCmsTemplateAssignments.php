<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsTemplate;
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Yack\Content\Domain\CmsTemplateAssignments;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\InvalidCmsTemplateAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Resource\Model\CodeAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(CmsTemplateAssignments::class)]
final readonly class DoctrineCmsTemplateAssignments implements CmsTemplateAssignments
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.yack.locale')]
        private string $locale,
    ) {
    }

    public function of(CmsTemplate $template): array
    {
        return [
            'products' => array_map(
                fn (Product $product): array => ['code' => (string) $product->getCode(), 'name' => (string) $product->getTranslation($this->locale)->getName()],
                $this->assignedProducts($template),
            ),
            'taxons' => array_map(
                fn (Taxon $taxon): array => ['code' => (string) $taxon->getCode(), 'name' => (string) $taxon->getTranslation($this->locale)->getName()],
                $this->assignedTaxons($template),
            ),
        ];
    }

    public function replace(CmsTemplate $template, array $productCodes, array $taxonCodes): void
    {
        $productCodes = self::clean($productCodes);
        $taxonCodes = self::clean($taxonCodes);
        $isProductTemplate = CmsTemplateType::Product === $template->getType();

        if (!$isProductTemplate && [] !== $productCodes) {
            throw new InvalidCmsTemplateAssignment('Un template catégorie ne s\'affecte qu\'à des catégories.', 'productCodes');
        }

        /** @var list<Product> $products */
        $products = $this->findByCodes(Product::class, $productCodes, 'Produit(s) inconnu(s) : %s.', 'productCodes');
        /** @var list<Taxon> $taxons */
        $taxons = $this->findByCodes(Taxon::class, $taxonCodes, 'Catégorie(s) inconnue(s) : %s.', 'taxonCodes');

        foreach ($this->assignedProducts($template) as $product) {
            if (!in_array($product->getCode(), $productCodes, true)) {
                $product->setCmsTemplate(null);
            }
        }
        foreach ($products as $product) {
            $product->setCmsTemplate($template);
        }

        foreach ($this->assignedTaxons($template) as $taxon) {
            if (!in_array($taxon->getCode(), $taxonCodes, true)) {
                $isProductTemplate ? $taxon->setProductCmsTemplate(null) : $taxon->setCmsTemplate(null);
            }
        }
        foreach ($taxons as $taxon) {
            $isProductTemplate ? $taxon->setProductCmsTemplate($template) : $taxon->setCmsTemplate($template);
        }

        $this->entityManager->flush();
    }

    /**
     * @return list<Product>
     */
    private function assignedProducts(CmsTemplate $template): array
    {
        if (CmsTemplateType::Product !== $template->getType()) {
            return [];
        }

        return $this->entityManager->getRepository(Product::class)->findBy(['cmsTemplate' => $template], ['code' => 'ASC']);
    }

    /**
     * @return list<Taxon>
     */
    private function assignedTaxons(CmsTemplate $template): array
    {
        $field = CmsTemplateType::Product === $template->getType() ? 'productCmsTemplate' : 'cmsTemplate';

        return $this->entityManager->getRepository(Taxon::class)->findBy([$field => $template], ['left' => 'ASC']);
    }

    /**
     * @template T of CodeAwareInterface
     *
     * @param class-string<T> $class
     * @param list<string>    $codes
     *
     * @return list<T>
     */
    private function findByCodes(string $class, array $codes, string $unknownMessage, string $field): array
    {
        if ([] === $codes) {
            return [];
        }

        $found = $this->entityManager->getRepository($class)->findBy(['code' => $codes]);
        $unknown = array_diff($codes, array_map(static fn (CodeAwareInterface $entity): string => (string) $entity->getCode(), $found));
        if ([] !== $unknown) {
            throw new InvalidCmsTemplateAssignment(sprintf($unknownMessage, implode(', ', $unknown)), $field);
        }

        return $found;
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private static function clean(array $codes): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $codes), static fn (string $code): bool => '' !== $code)));
    }
}
