<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Yack\Content\Domain\ContactRequest;
use App\Yack\Content\Domain\ContactRequestNotifier;
use App\Yack\Content\Domain\ContactRequestThrottle;
use App\Yack\Content\Domain\ContactRequestThrottled;

/**
 * /contact: checks the sender's limit, then hands the message over to Yack.
 */
final readonly class SubmitContactRequest
{
    public function __construct(
        private ContactRequestThrottle $throttle,
        private ContactRequestNotifier $notifier,
    ) {
    }

    /**
     * @throws ContactRequestThrottled
     */
    public function __invoke(ContactRequest $request, string $sender): void
    {
        if (!$this->throttle->tryConsume($sender)) {
            throw new ContactRequestThrottled();
        }

        $this->notifier->notify($request);
    }
}
