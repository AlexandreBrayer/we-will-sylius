<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Controller;

use App\Yack\Content\Infrastructure\Controller\Admin\BuilderAccessController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BuilderAccessControllerTest extends TestCase
{
    #[DataProvider('targets')]
    public function testTheTargetIsAPathOfThisSiteOnly(string $asked, string $expected): void
    {
        self::assertSame($expected, BuilderAccessController::target($asked));
    }

    /** @return iterable<string, array{string, string}> */
    public static function targets(): iterable
    {
        yield 'builder page' => ['/cms-admin/builder/politique-rse', '/cms-admin/builder/politique-rse'];
        yield 'draft preview' => ['/politique-rse?apercu=1', '/politique-rse?apercu=1'];
        yield 'nothing asked' => ['', BuilderAccessController::DEFAULT_TARGET];
        yield 'other host' => ['//evil.example/x', BuilderAccessController::DEFAULT_TARGET];
        yield 'backslash host' => ['/\\evil.example', BuilderAccessController::DEFAULT_TARGET];
        yield 'scheme' => ['https://evil.example', BuilderAccessController::DEFAULT_TARGET];
        yield 'space' => ['/cms-admin builder', BuilderAccessController::DEFAULT_TARGET];
    }
}
