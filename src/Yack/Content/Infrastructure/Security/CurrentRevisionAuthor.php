<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Security;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** Author of a revision: the logged-in administrator, null without one (fixtures, commands). */
final readonly class CurrentRevisionAuthor
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    public function get(): ?CmsRevisionAuthor
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AdminUser ? CmsRevisionAuthor::of($user) : null;
    }
}
