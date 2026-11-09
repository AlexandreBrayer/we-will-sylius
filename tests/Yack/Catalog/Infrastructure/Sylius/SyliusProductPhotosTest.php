<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Sylius;

use App\Yack\Catalog\Infrastructure\Sylius\SyliusProductPhotos;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Files written to a temporary folder: the generic visual, the photo, and the stored images (Sylius images_dir).
 *
 * @internal
 */
#[CoversClass(SyliusProductPhotos::class)]
final class SyliusProductPhotosTest extends TestCase
{
    private string $dir;

    /** @var ProductRepositoryInterface<ProductInterface>&MockObject */
    private ProductRepositoryInterface&MockObject $products;

    /** @var FactoryInterface<ImageInterface>&MockObject */
    private FactoryInterface&MockObject $imageFactory;

    private ImageUploaderInterface&MockObject $uploader;

    private EntityManagerInterface&MockObject $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/yack-photos-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/media/ab/cd', 0o777, true);
        file_put_contents($this->dir . '/console.png', 'visuel de repli');
        file_put_contents($this->dir . '/SRF50ZSX-W.jpg', 'photo du produit');
        $this->products = $this->createMock(ProductRepositoryInterface::class);
        $this->imageFactory = $this->createMock(FactoryInterface::class);
        $this->uploader = $this->createMock(ImageUploaderInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function testReplacesTheStoredGenericVisualByThePhoto(): void
    {
        file_put_contents($this->dir . '/media/ab/cd/visuel.png', 'visuel de repli');
        $image = $this->storedImage('ab/cd/visuel.png');
        $image->expects($this->once())->method('setFile')->with($this->callback(fn (File $file): bool => $this->dir . '/SRF50ZSX-W.jpg' === $file->getPathname()));
        $this->products->method('findOneBy')->with(['code' => 'SRF50ZSX-W'])->willReturn($this->product([$image]));
        $this->uploader->expects($this->once())->method('upload')->with($image);
        $this->entityManager->expects($this->once())->method('flush');

        $this->assertTrue($this->replace());
    }

    public function testKeepsAnImageThatIsNotTheGenericVisual(): void
    {
        file_put_contents($this->dir . '/media/ab/cd/choisie.png', 'image choisie dans le back-office');
        $this->products->method('findOneBy')->willReturn($this->product([$this->storedImage('ab/cd/choisie.png')]));
        $this->expectNoWrite();

        $this->assertFalse($this->replace());
    }

    public function testKeepsAnImageOfTheSameSizeWithOtherBytes(): void
    {
        file_put_contents($this->dir . '/media/ab/cd/meme-taille.png', 'visuel de rePLI');
        $this->products->method('findOneBy')->willReturn($this->product([$this->storedImage('ab/cd/meme-taille.png')]));
        $this->expectNoWrite();

        $this->assertFalse($this->replace());
    }

    public function testReplacesAnImageWhoseFileIsGone(): void
    {
        $image = $this->storedImage('ab/cd/disparue.png');
        $this->products->method('findOneBy')->willReturn($this->product([$image]));
        $this->uploader->expects($this->once())->method('upload')->with($image);

        $this->assertTrue($this->replace());
    }

    public function testGivesTheProductAnImageWhenItHasNone(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())->method('setType')->with('main');
        $this->imageFactory->method('createNew')->willReturn($image);
        $product = $this->product([]);
        $product->expects($this->once())->method('addImage')->with($image);
        $this->products->method('findOneBy')->willReturn($product);
        $this->uploader->expects($this->once())->method('upload')->with($image);
        $this->entityManager->expects($this->once())->method('flush');

        $this->assertTrue($this->replace());
    }

    public function testLeavesAProductWithSeveralImagesAlone(): void
    {
        $this->products->method('findOneBy')->willReturn($this->product([$this->storedImage('ab/cd/a.png'), $this->storedImage('ab/cd/b.png')]));
        $this->expectNoWrite();

        $this->assertFalse($this->replace());
    }

    public function testUnknownProduct(): void
    {
        $this->products->method('findOneBy')->willReturn(null);
        $this->expectNoWrite();

        $this->assertFalse($this->replace());
    }

    private function replace(): bool
    {
        $photos = new SyliusProductPhotos($this->products, $this->imageFactory, $this->uploader, $this->entityManager, $this->dir . '/media');

        return $photos->replaceFallback('SRF50ZSX-W', $this->dir . '/SRF50ZSX-W.jpg', $this->dir . '/console.png');
    }

    private function storedImage(string $path): ImageInterface&MockObject
    {
        $image = $this->createMock(ImageInterface::class);
        $image->method('getPath')->willReturn($path);

        return $image;
    }

    /** @param list<ImageInterface> $images */
    private function product(array $images): ProductInterface&MockObject
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getImages')->willReturn(new ArrayCollection($images));

        return $product;
    }

    private function expectNoWrite(): void
    {
        $this->uploader->expects($this->never())->method('upload');
        $this->entityManager->expects($this->never())->method('flush');
    }
}
