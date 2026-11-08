<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CharteGuard;
use App\Yack\Content\Domain\StyleNotAllowed;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Builder level of the author applied by the write processors of CMS content (D4, CharteGuard).
 * The author is the logged-in administrator; without one (fixtures, commands) the level is `libre`.
 * A refusal is a 422 on `blocks`, same shape as the content validation (CmsPage::validateBlocks()),
 * so the builder shows it like any other refused content.
 */
final readonly class BuilderLevelCheck
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    public function level(): BuilderLevel
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AdminUser ? $user->getBuilderLevel() : BuilderLevel::Libre;
    }

    /**
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $blocks   content written
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $previous stored version, [] when new
     *
     * @throws ValidationException a free value that the level of the author does not allow
     */
    public function assertAllowed(object $root, array $blocks, array $previous = []): void
    {
        try {
            CharteGuard::assertAllowed($this->level(), $blocks, $previous);
        } catch (StyleNotAllowed $exception) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(
                $exception->getMessage(),
                null,
                [],
                $root,
                'blocks',
                $exception->deviation->value,
            )]));
        }
    }
}
