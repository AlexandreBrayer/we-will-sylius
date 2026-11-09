<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsEditLockSituation;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsEditLockStatus;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Edit lock of a page or a template in the builder (D13; JWT of a Sylius administrator, ROLE_API_ACCESS;
 * 401 without it; 404 for an unknown content), as the administrator who asks sees it:
 *  - GET /api/v2/admin/cms-pages/{slug}/lock, GET /api/v2/admin/cms-templates/{id}/lock: reads it;
 *  - POST on the same routes {"action": "acquire" | "heartbeat" | "takeover" | "release", "token": "…"}:
 *    takes it at the opening, keeps it alive, « Prendre la main », gives it back (Application\CmsEditLocking).
 *    Always 200 with the lock after the operation (`state` says who holds it); 422 for an unknown action.
 * A save (PUT) of the content is refused with a 409 while another administrator holds the lock.
 */
#[ApiResource(
    shortName: 'CmsEditLock',
    operations: [
        new Get(
            uriTemplate: '/admin/cms-pages/{slug}/lock',
            uriVariables: ['slug' => new Link(fromClass: CmsPage::class, identifiers: ['slug'])],
            normalizationContext: ['groups' => ['cms_edit_lock:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsEditLockProvider::class,
            name: 'app_yack_api_admin_cms_page_lock_get',
        ),
        new Get(
            uriTemplate: '/admin/cms-templates/{id}/lock',
            uriVariables: ['id' => new Link(fromClass: CmsTemplate::class, identifiers: ['id'])],
            normalizationContext: ['groups' => ['cms_edit_lock:read']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: CmsEditLockProvider::class,
            name: 'app_yack_api_admin_cms_template_lock_get',
        ),
        new Post(
            uriTemplate: '/admin/cms-pages/{slug}/lock',
            uriVariables: ['slug' => new Link(fromClass: CmsPage::class, identifiers: ['slug'])],
            status: 200,
            itemUriTemplate: 'app_yack_api_admin_cms_page_lock_get',
            normalizationContext: ['groups' => ['cms_edit_lock:read']],
            denormalizationContext: ['groups' => ['cms_edit_lock:write']],
            security: "is_granted('ROLE_API_ACCESS')",
            input: CmsEditLockInput::class,
            read: false,
            validate: false,
            processor: CmsEditLockProcessor::class,
            name: 'app_yack_api_admin_cms_page_lock_post',
        ),
        new Post(
            uriTemplate: '/admin/cms-templates/{id}/lock',
            uriVariables: ['id' => new Link(fromClass: CmsTemplate::class, identifiers: ['id'])],
            status: 200,
            itemUriTemplate: 'app_yack_api_admin_cms_template_lock_get',
            normalizationContext: ['groups' => ['cms_edit_lock:read']],
            denormalizationContext: ['groups' => ['cms_edit_lock:write']],
            security: "is_granted('ROLE_API_ACCESS')",
            input: CmsEditLockInput::class,
            read: false,
            validate: false,
            processor: CmsEditLockProcessor::class,
            name: 'app_yack_api_admin_cms_template_lock_post',
        ),
    ],
)]
final class CmsEditLockView
{
    /**
     * @param array{firstName: ?string, lastName: ?string, name: string}|null $holder
     */
    public function __construct(
        /** page | template */
        #[Groups(['cms_edit_lock:read'])]
        public string $target,
        /** Slug of the page, or identifier of the template (as a string). */
        #[ApiProperty(identifier: true)]
        #[Groups(['cms_edit_lock:read'])]
        public string $targetId,
        /** free | mine | other (CmsEditLockSituation), for the administrator who asks. */
        #[Groups(['cms_edit_lock:read'])]
        public string $state,
        /** Administrator who holds the lock, null when it is free. */
        #[Groups(['cms_edit_lock:read'])]
        public ?array $holder,
        /** Start of the edit of the holder (ISO 8601), null when free. */
        #[Groups(['cms_edit_lock:read'])]
        public ?string $since,
        /** Last sign of life of the holder (ISO 8601), null when free. */
        #[Groups(['cms_edit_lock:read'])]
        public ?string $heartbeatAt,
        /** When the lock becomes free without a sign of life (ISO 8601), null when free. */
        #[Groups(['cms_edit_lock:read'])]
        public ?string $expiresAt,
        /** Token of the lock, only for its holder: sent with the signs of life and to give it back. */
        #[Groups(['cms_edit_lock:read'])]
        public ?string $token,
        /** Time of the server when it answered (ISO 8601). */
        #[Groups(['cms_edit_lock:read'])]
        public string $now,
        /** Seconds between two signs of life of the builder (CmsEditLockState::HEARTBEAT). */
        #[Groups(['cms_edit_lock:read'])]
        public int $heartbeatSeconds = CmsEditLockState::HEARTBEAT,
        /** Seconds without a sign of life after which the lock is free (CmsEditLockState::TIMEOUT). */
        #[Groups(['cms_edit_lock:read'])]
        public int $timeoutSeconds = CmsEditLockState::TIMEOUT,
    ) {
    }

    public static function of(CmsEditLockStatus $status, CmsPage|CmsTemplate $target): self
    {
        $held = CmsEditLockSituation::Free !== $status->situation ? $status->lock : null;

        return new self(
            $target instanceof CmsPage ? 'page' : 'template',
            $target instanceof CmsPage ? $target->getSlug() : (string) $target->getId(),
            $status->situation->value,
            null === $held ? null : ['firstName' => $held->holder->firstName, 'lastName' => $held->holder->lastName, 'name' => $held->holder->name],
            $held?->since->format(\DATE_ATOM),
            $held?->heartbeatAt?->format(\DATE_ATOM),
            $held?->expiresAt($status->at)?->format(\DATE_ATOM),
            CmsEditLockSituation::Mine === $status->situation ? $held?->token : null,
            $status->at->format(\DATE_ATOM),
        );
    }
}
