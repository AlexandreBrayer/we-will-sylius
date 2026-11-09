<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Sylius;

use App\Yack\Catalog\Domain\ProductPhotos;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Adapter: swaps the generic visual of a Sylius product for its photo. The stored image is recognised as the
 * generic visual by its content (same bytes as the visual file), so an image chosen in the back-office is never
 * replaced. The image keeps its entity: the uploader writes the new file, and the old one is removed once the new
 * path is saved.
 */
#[AsAlias(ProductPhotos::class)]
final readonly class SyliusProductPhotos implements ProductPhotos
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     * @param FactoryInterface<ImageInterface>             $productImageFactory
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.product')]
        private ProductRepositoryInterface $productRepository,
        #[Autowire(service: 'sylius.factory.product_image')]
        private FactoryInterface $productImageFactory,
        #[Autowire(service: 'sylius.uploader.image')]
        private ImageUploaderInterface $imageUploader,
        private EntityManagerInterface $entityManager,
        #[Autowire('%sylius_core.images_dir%')]
        private string $imagesDir,
    ) {
    }

    public function replaceFallback(string $productCode, string $photoPath, string $fallbackPath): bool
    {
        $product = $this->productRepository->findOneBy(['code' => $productCode]);
        if (!$product instanceof ProductInterface) {
            return false;
        }

        $images = $product->getImages();
        if ($images->count() > 1) {
            return false;
        }

        $image = $images->first();
        if (false === $image) {
            $image = $this->productImageFactory->createNew();
            $image->setType('main');
            $product->addImage($image);
        } elseif (!$this->isGenericVisual($image, $fallbackPath)) {
            return false;
        }

        // The previous file stays until the new path is saved: the shop never points to a missing file, even when
        // the save fails (the uploader would otherwise remove it before writing the photo).
        $previousPath = $image->getPath();
        $image->setPath(null);
        $image->setFile(new UploadedFile($photoPath, basename($photoPath), test: true));
        $this->imageUploader->upload($image);
        $this->entityManager->flush();

        if (null !== $previousPath && $previousPath !== $image->getPath() && is_file($this->imagesDir . '/' . $previousPath)) {
            $this->imageUploader->remove($previousPath);
        }

        return true;
    }

    /** The stored file has the bytes of the generic visual, or is gone (nothing to keep then). */
    private function isGenericVisual(ImageInterface $image, string $fallbackPath): bool
    {
        $path = $image->getPath();
        $stored = null === $path ? null : $this->imagesDir . '/' . $path;
        if (null === $stored || !is_file($stored)) {
            return true;
        }

        return filesize($stored) === filesize($fallbackPath) &&
            hash_file('sha256', $stored) === hash_file('sha256', $fallbackPath);
    }
}
