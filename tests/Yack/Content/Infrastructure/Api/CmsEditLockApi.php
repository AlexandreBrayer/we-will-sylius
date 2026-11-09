<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Tests\Yack\Content\Application\InMemoryCmsEditLocks;
use App\Tests\Yack\Content\Application\InMemoryCmsPages;
use App\Tests\Yack\Content\Application\InMemoryCmsTemplates;
use App\Yack\Content\Application\CmsEditLocking;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Api\CmsEditLockTargets;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/** Edit lock API wired on doubles: page « offre » (id 10), template 4, Sophie (id 1) and Paul (id 2). */
final class CmsEditLockApi
{
    public InMemoryCmsEditLocks $locks;

    public MockClock $clock;

    public CmsEditLocking $locking;

    public TokenStorage $tokens;

    public CmsPage $page;

    public CmsTemplate $template;

    public AdminUser $sophie;

    public AdminUser $paul;

    public function __construct()
    {
        $this->locks = new InMemoryCmsEditLocks();
        $this->clock = new MockClock('2026-11-08 14:05:00', 'UTC');
        $this->locking = new CmsEditLocking($this->locks, $this->clock);
        $this->tokens = new TokenStorage();
        $this->page = new CmsPage('offre', 'Offre');
        (new \ReflectionProperty(CmsPage::class, 'id'))->setValue($this->page, 10);
        $this->template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        (new \ReflectionProperty(CmsTemplate::class, 'id'))->setValue($this->template, 4);
        $this->sophie = self::admin(1, 'Sophie', 'Martin');
        $this->paul = self::admin(2, 'Paul', 'Durand');
    }

    public function targets(): CmsEditLockTargets
    {
        return new CmsEditLockTargets(new InMemoryCmsPages([$this->page]), new InMemoryCmsTemplates([$this->template]), $this->tokens);
    }

    public function logIn(?AdminUser $admin): void
    {
        $this->tokens->setToken(null === $admin ? null : new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));
    }

    public static function admin(int $id, string $firstName, string $lastName): AdminUser
    {
        $admin = new AdminUser();
        (new \ReflectionProperty(AdminUser::class, 'id'))->setValue($admin, $id);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);

        return $admin;
    }
}
