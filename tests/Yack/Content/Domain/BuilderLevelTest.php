<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BuilderLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BuilderLevel::class)]
final class BuilderLevelTest extends TestCase
{
    public function testLevelsHaveAStoredValueAndAFrenchLabel(): void
    {
        $this->assertSame(['charte', 'libre_avertissement', 'libre'], array_map(static fn (BuilderLevel $level): string => $level->value, BuilderLevel::cases()));
        $this->assertSame('Charte', BuilderLevel::Charte->label());
        $this->assertSame('Libre avec avertissement', BuilderLevel::LibreAvertissement->label());
        $this->assertSame('Libre', BuilderLevel::Libre->label());
    }

    public function testOnlyTheCharteLevelRefusesFreeValues(): void
    {
        $this->assertFalse(BuilderLevel::Charte->allowsFreeValues());
        $this->assertTrue(BuilderLevel::LibreAvertissement->allowsFreeValues());
        $this->assertTrue(BuilderLevel::Libre->allowsFreeValues());
    }

    public function testOnlyTheWarningLevelWarns(): void
    {
        $this->assertFalse(BuilderLevel::Charte->warnsOnFreeValues());
        $this->assertTrue(BuilderLevel::LibreAvertissement->warnsOnFreeValues());
        $this->assertFalse(BuilderLevel::Libre->warnsOnFreeValues());
    }

    public function testOnlyTheFreeLevelManagesLevels(): void
    {
        $this->assertFalse(BuilderLevel::Charte->canManageLevels());
        $this->assertFalse(BuilderLevel::LibreAvertissement->canManageLevels());
        $this->assertTrue(BuilderLevel::Libre->canManageLevels());
    }
}
