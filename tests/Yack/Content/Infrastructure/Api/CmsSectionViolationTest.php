<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Yack\Content\Domain\CmsSectionRefused;
use App\Yack\Content\Infrastructure\Api\CmsSectionInput;
use App\Yack\Content\Infrastructure\Api\CmsSectionViolation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsSectionViolation::class)]
final class CmsSectionViolationTest extends TestCase
{
    public function testOneViolationOnTheFieldWithTheFrenchMessage(): void
    {
        $root = new CmsSectionInput();

        $violations = CmsSectionViolation::of(CmsSectionRefused::blocks('Une section contient au moins un bloc.'), $root)->getConstraintViolationList();

        $this->assertCount(1, $violations);
        $this->assertSame(['blocks', 'Une section contient au moins un bloc.', $root], [$violations->get(0)->getPropertyPath(), $violations->get(0)->getMessage(), $violations->get(0)->getRoot()]);
    }
}
