<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Security;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Report of the charte deviations and bulk replacement (back-office, Controller\Admin\StyleDeviationController):
 * administrators of level `libre` only. The controller asks it on every request; the menu and the
 * dashboard ask it only to hide the link (`is_granted('YACK_STYLE_DEVIATIONS')`).
 *
 * @extends Voter<string, mixed>
 */
final class StyleDeviationVoter extends Voter
{
    public const ATTRIBUTE = 'YACK_STYLE_DEVIATIONS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof AdminUser && BuilderLevel::Libre === $user->getBuilderLevel();
    }
}
