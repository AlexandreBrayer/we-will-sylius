<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Controller\Admin;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One login for the back-office and the builder: an administrator logged into the Sylius admin
 * opens the SvelteKit builder (or a draft preview) without a second password. The controller
 * issues the admin API token of the logged-in administrator into the builder cookie (same origin:
 * nginx serves the admin and the front), then redirects to the asked path of the front.
 * Without an admin session, the admin firewall shows its login form and comes back here.
 */
final class BuilderAccessController extends AbstractController
{
    /** Cookie read by the builder (front/src/lib/server/adminAuth.ts, TOKEN_COOKIE). */
    public const TOKEN_COOKIE = 'yack_cms_token';

    public const DEFAULT_TARGET = '/cms-admin/builder';

    public function __construct(
        private readonly JWTTokenManagerInterface $tokens,
        #[Autowire(param: 'lexik_jwt_authentication.token_ttl')]
        private readonly int $tokenTtl,
    ) {
    }

    #[Route('/%sylius_admin.path_name%/yack/builder', name: 'app_yack_admin_builder', methods: ['GET'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = $this->getUser();
        if (!$admin instanceof AdminUserInterface) {
            throw $this->createAccessDeniedException();
        }

        $response = new RedirectResponse(self::target($request->query->getString('vers')));
        $response->headers->setCookie(Cookie::create(self::TOKEN_COOKIE)
            ->withValue($this->tokens->create($admin))
            ->withExpires(time() + $this->tokenTtl)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));

        return $response;
    }

    /** A path of this site only (`/…`, never `//host` nor a scheme); the builder otherwise. */
    public static function target(string $asked): string
    {
        return 1 === preg_match('#^/(?![/\\\\])[^\s]*$#', $asked) ? $asked : self::DEFAULT_TARGET;
    }
}
