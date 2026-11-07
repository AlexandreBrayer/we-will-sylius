<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Documentation\Application\RetrievePassages;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RetrievePassages::class)]
final class RetrievePassagesTest extends TestCase
{
    private InMemoryDocumentationCorpus $corpus;
    private RetrievePassages $retriever;

    #[\Override]
    protected function setUp(): void
    {
        $pac = ['YK-PAE-M08'];
        $clim = ['YK-PAA-K35'];
        $this->corpus = new InMemoryDocumentationCorpus([
            new Notice('Notice PAC (FICTIF)', 'PAC air/eau Evolys 8 kW', $pac, 'Longueur de liaison ?', [
                new Passage('Notice PAC (FICTIF)', 4, 'Puissance nominale : 8,0 kW. Charge de fluide : 1,30 kg de R32.', $pac, 'Caractéristiques'),
                new Passage('Notice PAC (FICTIF)', 12, 'La longueur maximale de la liaison hydraulique est de 25 m.', $pac, 'Liaisons'),
                new Passage('Notice PAC (FICTIF)', 12, 'Section des tubes : multicouche Ø 32 mm.', $pac, 'Liaisons'),
                new Passage('Notice PAC (FICTIF)', 18, 'E5 : défaut de communication entre les unités.', $pac, 'Codes erreur'),
            ]),
            new Notice('Notice clim (FICTIF)', 'Climatiseur mural Kaze 3,5 kW', $clim, null, [
                new Passage('Notice clim (FICTIF)', 8, 'Longueur de liaison frigorifique : 20 m au maximum.', $clim, 'Liaisons frigorifiques'),
                new Passage('Notice clim (FICTIF)', 17, 'Nettoyer les filtres à air toutes les deux semaines.', $clim, 'Entretien'),
                new Passage('Notice clim (FICTIF)', 18, 'Diamètre des tubes : 1/4 et 3/8 de pouce.', $clim, 'Liaisons frigorifiques'),
            ]),
        ]);
        $this->retriever = new RetrievePassages($this->corpus);
    }

    public function testBestPassageComesFirst(): void
    {
        $passages = $this->retriever->retrieve(Question::ask('Quelle est la longueur de liaison de la PAC 8 kW ?'), 3);

        $this->assertSame('Notice PAC (FICTIF)', $passages[0]->source);
        $this->assertSame(12, $passages[0]->page);
        $this->assertStringContainsString('25 m', $passages[0]->text);
    }

    public function testProductPageQuestionOnlyReadsTheNoticesOfTheProduct(): void
    {
        $passages = $this->retriever->retrieve(Question::ask('Longueur de liaison ?', 'YK-PAA-K35'), 3);

        $this->assertSame(['Notice clim (FICTIF)'], array_values(array_unique(array_map(static fn (Passage $p): string => $p->source, $passages))));
        $this->assertStringContainsString('20 m', $passages[0]->text);
    }

    public function testAProductWithoutNoticeFindsNothing(): void
    {
        $this->assertSame([], $this->retriever->retrieve(Question::ask('Longueur de liaison ?', 'YK-PAE-M06'), 3));
    }

    public function testAccentsCaseAndPluralsDoNotMatter(): void
    {
        $passages = $this->retriever->retrieve(Question::ask('NETTOYAGE DES FILTRES ?'), 3);

        $this->assertSame(17, $passages[0]->page);
    }

    public function testAtMostLimitPassages(): void
    {
        $this->assertCount(1, $this->retriever->retrieve(Question::ask('liaison'), 1));
        $this->assertSame([], $this->retriever->retrieve(Question::ask('liaison'), 0));
        $this->assertCount(RetrievePassages::DEFAULT_LIMIT, $this->retriever->retrieve(Question::ask('liaisons, tubes')));
    }

    #[DataProvider('irrelevantQuestions')]
    public function testIrrelevantQuestionFindsNothing(string $question): void
    {
        $this->assertSame([], $this->retriever->retrieve(Question::ask($question), 3));
    }

    /** @return \Iterator<string, array{string}> */
    public static function irrelevantQuestions(): \Iterator
    {
        yield 'hors corpus' => ['Quelle est la capitale du Portugal ?'];
        yield 'nom du produit seul' => ['Evolys 8 kW'];
        yield 'mots vides seuls' => ['Quelle est la ?'];
        yield 'nombre seul' => ['Et 8 ?'];
        yield 'moins de la moitié des mots' => ['Prix et délai de livraison du filtre ?'];
    }

    public function testCodeErrorQuestion(): void
    {
        $passages = $this->retriever->retrieve(Question::ask('Que veut dire le code erreur E5 ?', 'YK-PAE-M08'), 3);

        $this->assertSame(18, $passages[0]->page);
    }

    public function testTheCorpusIsIndexedOnce(): void
    {
        $this->retriever->retrieve(Question::ask('liaison'), 3);
        $this->retriever->retrieve(Question::ask('filtres'), 3);

        $this->assertSame(1, $this->corpus->reads);
    }

    public function testEqualScoresKeepTheCorpusOrder(): void
    {
        $retriever = new RetrievePassages(new InMemoryDocumentationCorpus([
            new Notice('N', 'Appareil', [], null, [new Passage('N', 1, 'Anode.'), new Passage('N', 2, 'Anode.')]),
        ]));

        $this->assertSame([1, 2], array_map(static fn (Passage $p): int => $p->page, $retriever->retrieve(Question::ask('anode'), 3)));
    }

    public function testEmptyCorpusFindsNothing(): void
    {
        $this->assertSame([], (new RetrievePassages(new InMemoryDocumentationCorpus([])))->retrieve(Question::ask('liaison'), 3));
    }
}
