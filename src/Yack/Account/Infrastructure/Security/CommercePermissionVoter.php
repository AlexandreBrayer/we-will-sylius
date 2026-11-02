<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Security;

use App\Yack\Account\Domain\CommercePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class CommercePermissionVoter extends Voter
{
    public function __construct(private readonly CustomerProfileResolver $profileResolver)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== CommercePermission::tryFrom($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $profile = $this->profileResolver->forUser($token->getUser());

        return CommercePermission::from($attribute)->isGrantedTo($profile);
    }
}
