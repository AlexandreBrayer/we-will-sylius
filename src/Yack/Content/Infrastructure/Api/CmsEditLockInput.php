<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use Symfony\Component\Serializer\Attribute\Groups;

/** Body of POST /admin/cms-pages/{slug}/lock and /admin/cms-templates/{id}/lock (CmsEditLockView). */
final class CmsEditLockInput
{
    /** acquire | heartbeat | takeover | release (Domain\CmsEditLockAction). */
    #[Groups(['cms_edit_lock:write'])]
    public ?string $action = null;

    /** Token of the lock received by its holder (heartbeat, release). */
    #[Groups(['cms_edit_lock:write'])]
    public ?string $token = null;
}
