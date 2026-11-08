<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CharteGuard;
use App\Yack\Content\Domain\StyleDeviation;
use App\Yack\Content\Domain\StyleNotAllowed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CharteGuard::class)]
final class CharteGuardTest extends TestCase
{
    public function testANewFreeValueIsRefusedAtTheCharteLevel(): void
    {
        try {
            CharteGuard::assertAllowed(BuilderLevel::Charte, [self::block(), self::block(['fond' => '#ff7700'])]);
            $this->fail('StyleNotAllowed expected');
        } catch (StyleNotAllowed $exception) {
            $this->assertSame('Bloc n°2 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.', $exception->getMessage());
            $this->assertSame('fond', $exception->deviation->property);
        }
    }

    public function testFreeLevelsAddFreeValues(): void
    {
        foreach ([BuilderLevel::LibreAvertissement, BuilderLevel::Libre] as $level) {
            CharteGuard::assertAllowed($level, [self::block(['fond' => '#ff7700', 'titre' => '40px'])]);
            CharteGuard::assertAllowed($level, [self::block(['fond' => '#00ff00'])], [self::block(['fond' => '#ff7700'])]);
        }
        $this->addToAssertionCount(4);
    }

    public function testCharteValuesAlwaysPass(): void
    {
        CharteGuard::assertAllowed(BuilderLevel::Charte, [self::block(['fond' => 'teal', 'titre' => 'xl', 'hautEspace' => 'l'])]);
        $this->addToAssertionCount(1);
    }

    /**
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $previous
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $blocks
     */
    #[DataProvider('provideAcceptedChanges')]
    public function testAStoredFreeValueMayStay(array $previous, array $blocks): void
    {
        $this->assertSame([], CharteGuard::newDeviations($blocks, $previous));
        CharteGuard::assertAllowed(BuilderLevel::Charte, $blocks, $previous);
    }

    /**
     * @return \Iterator<string, array{list<array{type: string, props: array<string, string>, style?: array<string, string>}>, list<array{type: string, props: array<string, string>, style?: array<string, string>}>}>
     */
    public static function provideAcceptedChanges(): \Iterator
    {
        $free = self::block(['fond' => '#ff7700', 'texte' => 'white']);
        $plain = self::block();

        yield 'unchanged' => [[$plain, $free], [$plain, $free]];
        yield 'other props and charte style changed' => [[$free], [self::block(['fond' => '#ff7700', 'texte' => 'dark', 'alignement' => 'centre'], 'Autre titre')]];
        yield 'block moved' => [[$plain, $free, $plain], [$free, $plain, $plain]];
        yield 'block inserted before' => [[$free], [$plain, self::block(['fond' => 'plum']), $free]];
        yield 'block deleted' => [[$plain, $free], [$plain]];
        yield 'free value removed' => [[$free], [self::block(['texte' => 'white'])]];
        yield 'value moved to another block' => [[$free, $plain], [$plain, self::block(['fond' => '#ff7700'], 'Autre')]];
        yield 'stored in capitals' => [[self::block(['fond' => '#FF7700'])], [self::block(['fond' => '#ff7700'])]];
        yield 'stored without style key' => [[['type' => 'texte-image', 'props' => []]], [self::block()]];
    }

    /**
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $previous
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $blocks
     */
    #[DataProvider('provideRefusedChanges')]
    public function testAnAddedOrChangedFreeValueIsRefused(array $previous, array $blocks, StyleDeviation $expected): void
    {
        $this->assertSame(
            [[$expected->block, $expected->property, $expected->value]],
            array_map(static fn (StyleDeviation $d): array => [$d->block, $d->property, $d->value], CharteGuard::newDeviations($blocks, $previous)),
        );

        $this->expectException(StyleNotAllowed::class);
        CharteGuard::assertAllowed(BuilderLevel::Charte, $blocks, $previous);
    }

    /**
     * @return \Iterator<string, array{list<array{type: string, props: array<string, string>, style?: array<string, string>}>, list<array{type: string, props: array<string, string>, style?: array<string, string>}>, StyleDeviation}>
     */
    public static function provideRefusedChanges(): \Iterator
    {
        $free = self::block(['fond' => '#ff7700']);
        $plain = self::block();

        yield 'new page' => [[], [$plain, $free], new StyleDeviation(2, 'fond', '#ff7700')];
        yield 'value changed' => [[$free], [self::block(['fond' => '#ff7701'])], new StyleDeviation(1, 'fond', '#ff7701')];
        yield 'same value on another property' => [[$free], [self::block(['texte' => '#ff7700'])], new StyleDeviation(1, 'texte', '#ff7700')];
        yield 'block duplicated' => [[$plain, $free], [$plain, $free, $free], new StyleDeviation(3, 'fond', '#ff7700')];
        yield 'second free value on the block' => [[$free], [self::block(['fond' => '#ff7700', 'hautEspace' => '120px'])], new StyleDeviation(1, 'hautEspace', '120px')];
        yield 'free title size' => [[$plain], [self::block(['titre' => '40px'])], new StyleDeviation(1, 'titre', '40px')];
    }

    /**
     * @param array<string, string> $style
     *
     * @return array{type: string, props: array<string, string>, style: array<string, string>}
     */
    private static function block(array $style = [], string $title = 'Titre'): array
    {
        return ['type' => 'texte-image', 'props' => ['title' => $title], 'style' => $style];
    }
}
