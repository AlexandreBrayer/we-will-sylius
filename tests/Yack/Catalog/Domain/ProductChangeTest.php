<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\CatalogProduct;
use App\Yack\Catalog\Domain\FieldChange;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Domain\SyncOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProductChange::class)]
#[CoversClass(CatalogProduct::class)]
final class ProductChangeTest extends TestCase
{
    public function testANewProductIsCreatedWithEveryFieldItSends(): void
    {
        $change = ProductChange::compare(null, self::source(attributes: ['puissance_kw' => 2.5, 'secteurs' => 'Résidentiel'], shortDescription: 'Accroche'));

        $this->assertSame(['SRK25ZS-W', 'Console murale', SyncOutcome::Created, 9], [$change->code, $change->name, $change->outcome, $change->fieldCount()]);
        $this->assertSame([
            ['field' => 'name', 'label' => 'Nom', 'before' => null, 'after' => 'Console murale'],
            ['field' => 'active', 'label' => 'Statut', 'before' => null, 'after' => 'Actif'],
            ['field' => 'price', 'label' => 'Prix HT', 'before' => null, 'after' => "1\u{a0}699,90\u{a0}€"],
            ['field' => 'stock', 'label' => 'Stock', 'before' => null, 'after' => '12'],
            ['field' => 'category', 'label' => 'Catégorie', 'before' => null, 'after' => 'pac_air_air'],
            ['field' => 'attribute.type_article', 'label' => 'Nature', 'before' => null, 'after' => 'Produit fini'],
            ['field' => 'attribute.puissance_kw', 'label' => 'Puissance (kW)', 'before' => null, 'after' => '2,5'],
            ['field' => 'attribute.secteurs', 'label' => 'Secteurs', 'before' => null, 'after' => 'Résidentiel'],
        ], array_map(static fn (FieldChange $field): array => $field->toArray(), array_slice($change->fields, 0, 8)));
        $this->assertSame('short_description', $change->fields[8]->field ?? null);
    }

    public function testTheSameProductIsUnchanged(): void
    {
        $change = ProductChange::compare(self::current(), self::source());

        $this->assertSame([SyncOutcome::Unchanged, []], [$change->outcome, $change->fields]);
    }

    public function testOnlyTheFieldsThatDifferAreListedAndAnAttributeYackDoesNotSendIsLeftAsIs(): void
    {
        $current = self::current(priceInCents: 159990, stock: null, attributes: ['type_article' => 'Produit fini', 'marque' => 'MHI']);

        $change = ProductChange::compare($current, self::source(description: ' Nouvelle description '));

        $this->assertSame(SyncOutcome::Updated, $change->outcome);
        $this->assertSame([
            ['field' => 'price', 'label' => 'Prix HT', 'before' => "1\u{a0}599,90\u{a0}€", 'after' => "1\u{a0}699,90\u{a0}€"],
            ['field' => 'stock', 'label' => 'Stock', 'before' => null, 'after' => '12'],
            ['field' => 'description', 'label' => 'Description', 'before' => null, 'after' => 'Nouvelle description'],
        ], array_map(static fn (FieldChange $field): array => $field->toArray(), $change->fields));
    }

    public function testAnActiveProductTheSourceSwitchesOffIsDisabled(): void
    {
        $change = ProductChange::compare(self::current(), self::source(active: false));

        $this->assertSame(SyncOutcome::Disabled, $change->outcome);
        $this->assertSame([['field' => 'active', 'label' => 'Statut', 'before' => 'Actif', 'after' => 'Inactif']], array_map(static fn (FieldChange $field): array => $field->toArray(), $change->fields));
    }

    public function testAnInactiveProductTheSourceSwitchesOnIsUpdated(): void
    {
        $this->assertSame(SyncOutcome::Updated, ProductChange::compare(self::current(active: false), self::source())->outcome);
    }

    public function testAnEmptyNameOrCategoryOfTheShopIsShownAsEmpty(): void
    {
        $change = ProductChange::compare(self::current(name: ' ', mainTaxonCode: null), self::source());

        $this->assertSame([['name', null, 'Console murale'], ['category', null, 'pac_air_air']], array_map(static fn (FieldChange $field): array => [$field->field, $field->before, $field->after], $change->fields));
    }

    #[DataProvider('provideTexts')]
    public function testTheTextOfAStoredValue(string|int|float|bool $value, string $text): void
    {
        $this->assertSame($text, ProductChange::text($value));
    }

    /** @return \Iterator<string, array{string|int|float|bool, string}> */
    public static function provideTexts(): \Iterator
    {
        yield 'vrai' => [true, 'oui'];
        yield 'faux' => [false, 'non'];
        yield 'décimal' => [4.62, '4,62'];
        yield 'flottant entier' => [3.0, '3'];
        yield 'entier' => [200, '200'];
        yield 'texte' => [' R32 ', 'R32'];
    }

    public function testAnAttributeWithoutLabelIsShownByItsCode(): void
    {
        $this->assertSame(['Documents', 'Capacité (L)', 'couleur'], [ProductChange::attributeLabel('documentation'), ProductChange::attributeLabel('capacite_litres'), ProductChange::attributeLabel('couleur')]);
    }

    public function testThePriceIsShownInEuros(): void
    {
        $this->assertSame(["0,00\u{a0}€", "12\u{a0}345,67\u{a0}€"], [ProductChange::price(0), ProductChange::price(1234567)]);
    }

    /** @param array<string, string|int|float|bool> $attributes */
    private static function source(bool $active = true, array $attributes = [], ?string $shortDescription = null, ?string $description = null): SourceProduct
    {
        return new SourceProduct('SRK25ZS-W', 'Console murale', $active, ProductKind::Finished, 'pac_air_air', 169990, 12, attributes: $attributes, shortDescription: $shortDescription, description: $description);
    }

    /** @param array<string, string|int|float|bool> $attributes */
    private static function current(string $name = 'Console murale', bool $active = true, ?int $priceInCents = 169990, ?int $stock = 12, ?string $mainTaxonCode = 'pac_air_air', array $attributes = ['type_article' => 'Produit fini']): CatalogProduct
    {
        return new CatalogProduct('SRK25ZS-W', $name, $active, $priceInCents, $stock, $mainTaxonCode, $attributes);
    }
}
