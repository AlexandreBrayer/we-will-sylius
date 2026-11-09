<?php

declare(strict_types=1);

namespace App\Entity\Content;

use App\Entity\User\AdminUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * Edit lock of a CMS page or template in the builder (D13, Content\Domain\CmsEditLocks): the administrator
 * who edits it, since when, his last sign of life. Exactly one of `page` and `template` is set, each at most
 * once (unique target: two administrators never insert two locks for the same content). The row stays when
 * the lock is given back (`heartbeat_at` null) or goes silent: the next holder replaces it. Deleting the
 * content or the administrator deletes the lock (ON DELETE CASCADE).
 *
 * Written by Persistence\DoctrineCmsEditLocks only (compare and set on `version`); read as
 * Domain\CmsEditLockState. API: App\Yack\Content\Infrastructure\Api\CmsEditLockView.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_cms_edit_lock')]
#[ORM\UniqueConstraint(name: 'uniq_yack_cms_edit_lock_page', columns: ['page_id'])]
#[ORM\UniqueConstraint(name: 'uniq_yack_cms_edit_lock_template', columns: ['template_id'])]
class CmsEditLock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CmsPage::class)]
    #[ORM\JoinColumn(name: 'page_id', nullable: true, onDelete: 'CASCADE')]
    private ?CmsPage $page = null;

    #[ORM\ManyToOne(targetEntity: CmsTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: true, onDelete: 'CASCADE')]
    private ?CmsTemplate $template = null;

    /** Administrator who holds (or last held) the lock. */
    #[ORM\ManyToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(name: 'admin_id', nullable: false, onDelete: 'CASCADE')]
    private AdminUser $admin;

    /** Drawn each time the lock changes hands; the editor of the holder sends it with its signs of life. */
    #[ORM\Column(type: 'string', length: 32)]
    private string $token;

    /** Start of the edit of the holder (« modifie cette page depuis 14 h 05 »). */
    #[ORM\Column(name: 'acquired_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $acquiredAt;

    /** Last sign of life; null once given back. */
    #[ORM\Column(name: 'heartbeat_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $heartbeatAt;

    /** Grows at each write (compare and set). */
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(CmsPage|CmsTemplate $target, AdminUser $admin, string $token, \DateTimeImmutable $now)
    {
        if ($target instanceof CmsPage) {
            $this->page = $target;
        } else {
            $this->template = $target;
        }
        $this->admin = $admin;
        $this->token = $token;
        $this->acquiredAt = $now;
        $this->heartbeatAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): ?CmsPage
    {
        return $this->page;
    }

    public function getTemplate(): ?CmsTemplate
    {
        return $this->template;
    }

    public function getAdmin(): AdminUser
    {
        return $this->admin;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getAcquiredAt(): \DateTimeImmutable
    {
        return $this->acquiredAt;
    }

    public function getHeartbeatAt(): ?\DateTimeImmutable
    {
        return $this->heartbeatAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
