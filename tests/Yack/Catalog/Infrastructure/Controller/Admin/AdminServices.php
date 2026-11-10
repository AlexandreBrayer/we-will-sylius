<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Controller\Admin;

use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Services of an admin controller without the kernel: Twig on templates given by the test, URLs as
 * « /route?parameters », a session for the flashes, the CSRF token « valide » only, the user « admin ».
 */
final class AdminServices
{
    public const VALID_TOKEN = 'valide';

    public readonly Container $container;

    public readonly Session $session;

    private readonly RequestStack $requestStack;

    /**
     * @param RouterInterface&Stub             $router stub of the test, configured here
     * @param CsrfTokenManagerInterface&Stub   $csrf   stub of the test, configured here
     * @param array<string, string>            $templates
     */
    public function __construct(RouterInterface&Stub $router, CsrfTokenManagerInterface&Stub $csrf, array $templates, bool $signedIn = true)
    {
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => '/' . $route . ([] === $parameters ? '' : '?' . http_build_query($parameters)));
        $csrf->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => self::VALID_TOKEN === $token->getValue());
        $tokens = new TokenStorage();
        if ($signedIn) {
            $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMINISTRATION_ACCESS']), 'admin', ['ROLE_ADMINISTRATION_ACCESS']));
        }
        $this->session = new Session(new MockArraySessionStorage());
        $this->requestStack = new RequestStack();
        $this->request(new Request());

        $this->container = new Container();
        $this->container->set('twig', new Environment(new ArrayLoader($templates), ['strict_variables' => true, 'autoescape' => false]));
        $this->container->set('router', $router);
        $this->container->set('security.csrf.token_manager', $csrf);
        $this->container->set('security.token_storage', $tokens);
        $this->container->set('request_stack', $this->requestStack);
    }

    /** The request of the action, current request of the stack (flashes go to its session). */
    public function request(Request $request): Request
    {
        $request->setSession($this->session);
        $this->requestStack->push($request);

        return $request;
    }

    /** @return array<string, list<mixed>> */
    public function flashes(): array
    {
        return $this->session->getFlashBag()->all();
    }
}
