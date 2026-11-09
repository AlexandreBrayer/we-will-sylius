<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Validator;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsMediaLibrary;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\PageBlocks;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates the blocks of an entity with the media library: an entity cannot receive a service, a
 * constraint validator can (Symfony service, autowired). The rule stays in the Domain
 * (PageBlocks::normalize()), which receives the port CmsMediaLibrary as an argument. The builder level
 * of the author is not checked here: the write processors apply it (BuilderLevelCheck).
 */
final class ValidCmsBlocksValidator extends ConstraintValidator
{
    public function __construct(private readonly CmsMediaLibrary $library)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCmsBlocks) {
            throw new UnexpectedTypeException($constraint, ValidCmsBlocks::class);
        }
        if (!$value instanceof CmsPage && !$value instanceof CmsTemplate) {
            throw new UnexpectedValueException($value, CmsPage::class . '|' . CmsTemplate::class);
        }

        try {
            PageBlocks::normalize($value->getBlocks(), $value instanceof CmsTemplate ? $value->getType() : null, media: $this->library);
        } catch (InvalidPageBlocks $exception) {
            $this->context->buildViolation($exception->getMessage())->atPath('blocks')->addViolation();
        }
    }
}
