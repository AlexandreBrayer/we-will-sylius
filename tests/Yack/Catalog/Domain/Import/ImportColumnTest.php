<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\InvalidSourceProduct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImportColumn::class)]
final class ImportColumnTest extends TestCase
{
    public function testEachColumnIsRecognisedByItsTemplateHeaderAndByEachOfItsAliases(): void
    {
        foreach (ImportColumn::cases() as $column) {
            $this->assertSame($column, ImportColumn::fromHeader($column->header()), $column->header());
            foreach ($column->aliases() as $alias) {
                $this->assertSame($column, ImportColumn::fromHeader(mb_strtoupper($alias)), $alias);
            }
        }
    }

    public function testTheRequiredColumns(): void
    {
        $this->assertSame(
            ['code', 'nom', 'nature', 'actif', 'categorie', 'prix_ht', 'stock'],
            array_values(array_map(static fn (ImportColumn $column): string => $column->value, array_filter(ImportColumn::cases(), static fn (ImportColumn $column): bool => $column->required()))),
        );
    }

    public function testTheExampleLineOfTheTemplatesIsAValidRecordOfTheFeed(): void
    {
        $record = [];
        foreach (ImportColumn::cases() as $column) {
            $column->put($record, $column->example());
        }

        $this->assertSame([
            'sku' => 'SRK25ZS-W',
            'label' => 'SRK25ZS-W Mural - 2,5kW - R32',
            'type' => 'finished',
            'status' => 'active',
            'category' => 'pac_air_air_residentiel_ui_mural',
            'brand' => 'MHI',
            'price' => ['amount' => '594.90'],
            'stock' => 12,
            'range' => 'Pompe à Chaleur Air/Air',
            'model' => 'SRK25',
            'typology' => 'Mural',
            'specs' => ['power_kw' => 2.5, 'refrigerant' => 'R32'],
            'sectors' => ['Résidentiel', 'Petit Tertiaire'],
            'documents' => ['Notice d\'installation', 'Notice d\'utilisation'],
            'short_description' => 'Ligne esthétique conçue par un cabinet spécialisé.',
        ], $record);
    }

    #[DataProvider('provideHeaders')]
    public function testAHeaderIsReadAccentsCasePunctuationAndUnitsAside(string $header, string $normalized, ?ImportColumn $column): void
    {
        $this->assertSame([$normalized, $column], [ImportColumn::normalize($header), ImportColumn::fromHeader($header)]);
    }

    /** @return \Iterator<string, array{string, string, ImportColumn|null}> */
    public static function provideHeaders(): \Iterator
    {
        yield 'unité entre parenthèses' => ['Prix HT (€)', 'prix ht', ImportColumn::Price];
        yield 'accents et soulignés' => ['  Référence_Constructeur ', 'reference constructeur', ImportColumn::OemReference];
        yield 'anglais' => ['Product Name', 'product name', ImportColumn::Name];
        yield 'inconnu' => ['Couleur', 'couleur', null];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    #[DataProvider('provideCells')]
    public function testACellIsWrittenIntoTheRecordOfTheFeed(ImportColumn $column, string $cell, array $before, array $after): void
    {
        $record = $before;
        $column->put($record, $cell);

        $this->assertSame($after, $record);
    }

    /** @return \Iterator<string, array{ImportColumn, string, array<string, mixed>, array<string, mixed>}> */
    public static function provideCells(): \Iterator
    {
        yield 'texte rogné' => [ImportColumn::Name, ' Console ', [], ['label' => 'Console']];
        yield 'prix à la française' => [ImportColumn::Price, "1\u{a0}699,90", ['price' => ['currency' => 'EUR']], ['price' => ['currency' => 'EUR', 'amount' => '1699.90']]];
        yield 'prix avec point des milliers' => [ImportColumn::Price, '1.699,90', [], ['price' => ['amount' => '1699.90']]];
        yield 'prix avec virgule des milliers' => [ImportColumn::Price, '1,699.90', [], ['price' => ['amount' => '1699.90']]];
        yield 'prix en euros' => [ImportColumn::Price, '12 €', [], ['price' => ['amount' => '12']]];
        yield 'puissance décimale' => [ImportColumn::Power, '2,5', [], ['specs' => ['power_kw' => 2.5]]];
        yield 'scop' => [ImportColumn::Scop, '4.62', [], ['specs' => ['scop' => 4.62]]];
        yield 'stock entier' => [ImportColumn::Stock, '12,0', [], ['stock' => 12]];
        yield 'capacité' => [ImportColumn::Capacity, '200', ['specs' => 'pas un objet'], ['specs' => ['capacity_l' => 200]]];
        yield 'actif' => [ImportColumn::Active, 'Oui', [], ['status' => 'active']];
        yield 'inactif' => [ImportColumn::Active, 'non', [], ['status' => 'inactive']];
        yield 'pièce détachée' => [ImportColumn::Kind, 'Pièce détachée', [], ['type' => 'spare_part']];
        yield 'produit fini en anglais' => [ImportColumn::Kind, 'Finished product', [], ['type' => 'finished']];
        yield 'liste' => [ImportColumn::Sectors, 'Résidentiel; Tertiaire| ,', [], ['sectors' => ['Résidentiel', 'Tertiaire']]];
        yield 'cellule facultative vide' => [ImportColumn::Brand, ' ', ['brand' => 'MHI', 'sku' => 'X'], ['sku' => 'X']];
        yield 'caractéristique facultative vide' => [ImportColumn::Power, '', ['specs' => ['power_kw' => 2.5, 'scop' => 4.0]], ['specs' => ['scop' => 4.0]]];
    }

    #[DataProvider('provideInvalidCells')]
    public function testACellThatDoesNotHoldAValueOfTheColumnIsRefused(ImportColumn $column, string $cell, string $message): void
    {
        $record = [];

        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage($message);

        $column->put($record, $cell);
    }

    /** @return \Iterator<string, array{ImportColumn, string, string}> */
    public static function provideInvalidCells(): \Iterator
    {
        yield 'obligatoire vide' => [ImportColumn::Code, ' ', 'colonne « Code » vide.'];
        yield 'pas un nombre' => [ImportColumn::Price, 'abc', 'colonne « Prix HT » : nombre attendu (« abc »).'];
        yield 'pas un entier' => [ImportColumn::Stock, '1,5', 'colonne « Stock » : nombre entier attendu (« 1,5 »).'];
        yield 'ni oui ni non' => [ImportColumn::Active, 'peut-être', 'colonne « Actif » : oui ou non attendu (« peut-être »).'];
        yield 'nature inconnue' => [ImportColumn::Kind, 'kit', 'colonne « Nature » : « Produit fini » ou « Pièce détachée » attendu (« kit »).'];
    }

    public function testWriteSetsOrRemovesAField(): void
    {
        $record = ['label' => 'Console', 'specs' => ['scop' => 4.0]];
        ImportColumn::Name->write($record, null);
        ImportColumn::Refrigerant->write($record, 'R32');
        ImportColumn::Scop->write($record, null);

        $this->assertSame(['specs' => ['refrigerant' => 'R32']], $record);
    }

    /** @param array<mixed> $record */
    #[DataProvider('provideRecords')]
    public function testAValueOfTheRecordIsReadAsAnAdministratorTypesIt(ImportColumn $column, array $record, string $cell): void
    {
        $this->assertSame($cell, $column->read($record));
    }

    /** @return \Iterator<string, array{ImportColumn, array<mixed>, string}> */
    public static function provideRecords(): \Iterator
    {
        yield 'absent' => [ImportColumn::Name, [], ''];
        yield 'texte' => [ImportColumn::Name, ['label' => 'Console'], 'Console'];
        yield 'booléen' => [ImportColumn::Name, ['label' => true], '1'];
        yield 'ni texte ni nombre' => [ImportColumn::Name, ['label' => new \stdClass()], ''];
        yield 'actif' => [ImportColumn::Active, ['status' => 'active'], 'oui'];
        yield 'inactif' => [ImportColumn::Active, ['status' => 'inactive'], 'non'];
        yield 'statut gardé tel que saisi' => [ImportColumn::Active, ['status' => 'peut-être'], 'peut-être'];
        yield 'statut qui n\'est pas un texte' => [ImportColumn::Active, ['status' => 1], ''];
        yield 'nature connue' => [ImportColumn::Kind, ['type' => 'spare_part'], 'Pièce détachée'];
        yield 'nature inconnue' => [ImportColumn::Kind, ['type' => 'kit'], 'kit'];
        yield 'nature qui n\'est pas un texte' => [ImportColumn::Kind, ['type' => 5], ''];
        yield 'liste' => [ImportColumn::Sectors, ['sectors' => ['Résidentiel', 3, 'Tertiaire']], 'Résidentiel, Tertiaire'];
        yield 'prix en texte' => [ImportColumn::Price, ['price' => ['amount' => '594.90']], '594,90'];
        yield 'prix en nombre' => [ImportColumn::Price, ['price' => ['amount' => 594.9]], '594,9'];
        yield 'prix sans objet' => [ImportColumn::Price, ['price' => '594.90'], ''];
        yield 'stock' => [ImportColumn::Stock, ['stock' => 12], '12'];
        yield 'stock en texte' => [ImportColumn::Stock, ['stock' => '1.5'], '1.5'];
    }
}
