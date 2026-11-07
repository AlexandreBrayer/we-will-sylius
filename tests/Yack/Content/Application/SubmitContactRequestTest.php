<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Yack\Content\Application\SubmitContactRequest;
use App\Yack\Content\Domain\ContactRequest;
use App\Yack\Content\Domain\ContactRequestNotifier;
use App\Yack\Content\Domain\ContactRequestThrottle;
use App\Yack\Content\Domain\ContactRequestThrottled;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SubmitContactRequest::class)]
#[CoversClass(ContactRequestThrottled::class)]
final class SubmitContactRequestTest extends TestCase
{
    private ContactRequest $request;

    #[\Override]
    protected function setUp(): void
    {
        $this->request = ContactRequest::submit('Julie', 'julie@clim-test.example', null, null, null, 'Un message assez long.');
    }

    public function testTheMessageIsSentWhenTheSenderIsUnderTheLimit(): void
    {
        $throttle = new FakeContactRequestThrottle(allow: true);
        $notifier = new FakeContactRequestNotifier();

        (new SubmitContactRequest($throttle, $notifier))($this->request, '203.0.113.7');

        $this->assertSame(['203.0.113.7'], $throttle->senders);
        $this->assertCount(1, $notifier->sent);
        $this->assertSame($this->request, $notifier->sent[0]);
    }

    public function testTheMessageIsNotSentOverTheLimit(): void
    {
        $notifier = new FakeContactRequestNotifier();

        $this->expectException(ContactRequestThrottled::class);
        $this->expectExceptionMessage('Trop de messages envoyés');

        try {
            (new SubmitContactRequest(new FakeContactRequestThrottle(allow: false), $notifier))($this->request, '203.0.113.7');
        } finally {
            $this->assertCount(0, $notifier->sent);
        }
    }
}

/**
 * @internal
 */
final class FakeContactRequestThrottle implements ContactRequestThrottle
{
    /** @var list<string> */
    public array $senders = [];

    public function __construct(private readonly bool $allow)
    {
    }

    public function tryConsume(string $sender): bool
    {
        $this->senders[] = $sender;

        return $this->allow;
    }
}

/**
 * @internal
 */
final class FakeContactRequestNotifier implements ContactRequestNotifier
{
    /** @var list<ContactRequest> */
    public array $sent = [];

    public function notify(ContactRequest $request): void
    {
        $this->sent[] = $request;
    }
}
