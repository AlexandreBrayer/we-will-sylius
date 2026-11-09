<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Content\Infrastructure\Api\CmsEditLockInput;
use App\Yack\Content\Infrastructure\Api\CmsEditLockProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsEditLockProcessor::class)]
final class CmsEditLockProcessorTest extends TestCase
{
    public function testTheFourActionsOfTheBuilder(): void
    {
        $api = new CmsEditLockApi();
        $processor = new CmsEditLockProcessor($api->locking, $api->targets());

        $api->logIn($api->sophie);
        $sophie = $processor->process(self::input('acquire'), new Post(), ['slug' => 'offre']);
        $this->assertSame('mine', $sophie->state);

        $api->logIn($api->paul);
        $this->assertSame('other', $processor->process(self::input('acquire'), new Post(), ['slug' => 'offre'])->state);
        $paul = $processor->process(self::input('takeover'), new Post(), ['slug' => 'offre']);
        $this->assertSame('mine', $paul->state);

        $api->logIn($api->sophie);
        $api->clock->sleep(30);
        $told = $processor->process(self::input('heartbeat', $sophie->token), new Post(), ['slug' => 'offre']);
        $this->assertSame('other', $told->state);
        $this->assertSame('Paul Durand', $told->holder['name'] ?? null);

        $api->logIn($api->paul);
        $this->assertSame('free', $processor->process(self::input('release', $paul->token), new Post(), ['slug' => 'offre'])->state);
    }

    public function testATemplateHasItsLockToo(): void
    {
        $api = new CmsEditLockApi();
        $api->logIn($api->paul);

        $view = (new CmsEditLockProcessor($api->locking, $api->targets()))->process(self::input('acquire'), new Post(), ['id' => '4']);

        $this->assertSame('template', $view->target);
        $this->assertSame('mine', $view->state);
    }

    public function testAnEmptyTokenIsNoToken(): void
    {
        $api = new CmsEditLockApi();
        $api->logIn($api->paul);
        $processor = new CmsEditLockProcessor($api->locking, $api->targets());
        $processor->process(self::input('acquire'), new Post(), ['slug' => 'offre']);

        $this->assertSame('mine', $processor->process(self::input('release', ''), new Post(), ['slug' => 'offre'])->state);
    }

    public function testAnUnknownActionIsRefused(): void
    {
        $api = new CmsEditLockApi();
        $api->logIn($api->paul);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('action: Action inconnue : acquire, heartbeat, takeover ou release.');
        (new CmsEditLockProcessor($api->locking, $api->targets()))->process(self::input('steal'), new Post(), ['slug' => 'offre']);
    }

    public function testAMissingActionIsRefused(): void
    {
        $api = new CmsEditLockApi();
        $api->logIn($api->paul);

        $this->expectException(ValidationException::class);
        (new CmsEditLockProcessor($api->locking, $api->targets()))->process(new CmsEditLockInput(), new Post(), ['slug' => 'offre']);
    }

    private static function input(string $action, ?string $token = null): CmsEditLockInput
    {
        $input = new CmsEditLockInput();
        $input->action = $action;
        $input->token = $token;

        return $input;
    }
}
