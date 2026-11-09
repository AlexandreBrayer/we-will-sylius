<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Security;

use App\Entity\User\AdminUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * « Direction » pages of the back-office (Pilotage, SAV et pièces, Clients et encours): the administrator only, with
 * the rule of the other Yack screens reserved to him (report of the charte deviations): an administrator of builder
 * level `libre` (admin@yack.test in the demo). Level read as data on the AdminUser entity. The controller asks it on
 * every request (403 otherwise), the menu to hide the group.
 *
 * @extends Voter<string, mixed>
 */
final class SteeringVoter extends Voter
{
    public const ATTRIBUTE = 'YACK_STEERING';

    /** Value of the builder level that opens the pages (Content\Domain\BuilderLevel::Libre). */
    private const ADMINISTRATOR_LEVEL = 'libre';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof AdminUser && self::ADMINISTRATOR_LEVEL === $user->getBuilderLevel()->value;
    }
}
