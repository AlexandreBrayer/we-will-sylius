<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsTemplates;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Content of a lock route (page `slug` or template `id`) and the administrator who asks (CmsEditLockView). */
final readonly class CmsEditLockTargets
{
    public function __construct(
        private CmsPages $pages,
        private CmsTemplates $templates,
        private TokenStorageInterface $tokens,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @throws NotFoundHttpException unknown page or template
     */
    public function target(array $uriVariables): CmsPage|CmsTemplate
    {
        $slug = $uriVariables['slug'] ?? null;
        $id = $uriVariables['id'] ?? null;
        $target = match (true) {
            \is_string($slug) => $this->pages->find($slug),
            is_numeric($id) => $this->templates->find((int) $id),
            default => null,
        };

        return $target ?? throw new NotFoundHttpException(\is_string($slug) ? 'Page introuvable.' : 'Template introuvable.');
    }

    /** Logged-in administrator, null without one (fixtures, commands). */
    public function current(): ?AdminUser
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AdminUser ? $user : null;
    }

    /** @throws AccessDeniedException not an administrator */
    public function admin(): AdminUser
    {
        return $this->current() ?? throw new AccessDeniedException('Réservé aux administrateurs.');
    }
}
