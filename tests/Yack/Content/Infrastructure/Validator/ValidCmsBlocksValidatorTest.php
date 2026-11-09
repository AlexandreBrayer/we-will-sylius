<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Validator;

use App\Entity\Content\CmsMedia;
use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Validator\ValidCmsBlocks;
use App\Yack\Content\Infrastructure\Validator\ValidCmsBlocksValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 *
 * @extends ConstraintValidatorTestCase<ValidCmsBlocksValidator>
 */
#[CoversClass(ValidCmsBlocksValidator::class)]
#[CoversClass(ValidCmsBlocks::class)]
final class ValidCmsBlocksValidatorTest extends ConstraintValidatorTestCase
{
    private const IN_LIBRARY = 'media/yack-cms/0123456789abcdef0123456789abcdef.jpg';

    private const MISSING = 'media/yack-cms/ffffffffffffffffffffffffffffffff.jpg';

    public function testAPageWithAnImageOfTheLibraryOrAHistoricalVisualIsValid(): void
    {
        $this->validator->validate(self::page(self::IN_LIBRARY, 'yack/cms/hero.jpg'), new ValidCmsBlocks());

        $this->assertNoViolation();
    }

    public function testAPageWithAnImageMissingFromTheLibraryIsRefusedOnBlocks(): void
    {
        $this->validator->validate(self::page('yack/cms/hero.jpg', self::MISSING), new ValidCmsBlocks());

        $this->buildViolation('Bloc n°2 (Texte + image) : « ' . self::MISSING . ' » n\'est pas une valeur autorisée pour « image » : ni une image de la médiathèque, ni un visuel Yack.')
            ->atPath('property.path.blocks')
            ->assertRaised();
    }

    public function testATemplateIsCheckedWithItsType(): void
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        $template->setBlocks([['type' => 'produit-achat', 'props' => []], ['type' => 'tuiles', 'props' => ['image2' => self::IN_LIBRARY]]]);
        $this->validator->validate($template, new ValidCmsBlocks());
        $this->assertNoViolation();

        $template->setBlocks([['type' => 'tuiles', 'props' => ['image2' => self::IN_LIBRARY]]]);
        $this->validator->validate($template, new ValidCmsBlocks());
        $this->buildViolation('Un template produit doit contenir le bloc « Achat (prix, stock, panier) ».')->atPath('property.path.blocks')->assertRaised();
    }

    public function testMalformedContentIsRefusedOnce(): void
    {
        $page = new CmsPage('essai', 'Essai');
        $page->setBlocks([['type' => 'html', 'props' => ['code' => '<script>']]]);

        $this->validator->validate($page, new ValidCmsBlocks());

        $this->buildViolation('Bloc n°1 : type « html » inconnu.')->atPath('property.path.blocks')->assertRaised();
    }

    public function testItOnlyValidatesPagesAndTemplatesWithItsConstraint(): void
    {
        $this->assertSame(ValidCmsBlocks::CLASS_CONSTRAINT, (new ValidCmsBlocks())->getTargets());

        try {
            $this->validator->validate(new \stdClass(), new ValidCmsBlocks());
            $this->fail('Only pages and templates have blocks.');
        } catch (UnexpectedValueException) {
        }

        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(new CmsPage(), new NotBlank());
    }

    #[\Override]
    protected function createValidator(): ValidCmsBlocksValidator
    {
        $library = new InMemoryCmsMediaLibrary();
        $library->add(new CmsMedia(self::IN_LIBRARY, 'photo.jpg', CmsMediaFile::read(CmsMediaImages::jpeg())));

        return new ValidCmsBlocksValidator($library);
    }

    private static function page(string $first, string $second): CmsPage
    {
        $page = new CmsPage('essai', 'Essai');
        $page->setBlocks([['type' => 'texte-image', 'props' => ['image' => $first]], ['type' => 'texte-image', 'props' => ['image' => $second]]]);

        return $page;
    }
}
