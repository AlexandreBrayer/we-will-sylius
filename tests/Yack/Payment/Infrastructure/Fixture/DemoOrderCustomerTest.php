<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Fixture;

use App\Yack\Payment\Infrastructure\Fixture\DemoOrderCustomer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DemoOrderCustomer::class)]
final class DemoOrderCustomerTest extends TestCase
{
    public function testItKeepsWhatThePlannerNeeds(): void
    {
        $registeredAt = new \DateTimeImmutable('2025-01-02 09:00');
        $customer = new DemoOrderCustomer('a@exemple.test', $registeredAt, true, 3, 0);

        $this->assertSame(['a@exemple.test', $registeredAt, true, 3, 0], [$customer->email, $customer->registeredAt, $customer->business, $customer->minimumOrders, $customer->weight]);
    }
}
