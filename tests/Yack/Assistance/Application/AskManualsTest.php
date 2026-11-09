<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Application;

use App\Yack\Assistance\Application\AskManuals;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualAnswer;
use App\Yack\Assistance\Domain\ManualAnswerer;
use App\Yack\Assistance\Domain\ManualLibrary;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Base écrite à la main (documents inventés) et answerer qui compte ses appels : tourne sans vendor/.
 *
 * @internal
 */
#[CoversClass(AskManuals::class)]
final class AskManualsTest extends TestCase
{
    /** @var list<array{question: string, manuals: list<string>}> */
    private array $calls = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->calls = [];
    }

    public function testOnlyTheExactAndSeriesDocumentsWithTextAreRead(): void
    {
        $answer = $this->ask('  Comment   nettoyer le filtre ? ', 'srk25zs w');

        $this->assertSame('Réponse de test.', $answer->text);
        $this->assertSame([[
            'question' => 'Comment nettoyer le filtre ?',
            'manuals' => ['srk-zs-w-utilisation', 'srk-zs-wf-technique'],
        ]], $this->calls);
    }

    public function testAnotherCapacityOfTheSeriesIsRead(): void
    {
        $this->ask('Comment nettoyer le filtre ?', 'SRK50ZS-W');

        $this->assertSame(['srk-zs-wf-technique', 'srk-zs-w-utilisation'], $this->calls[0]['manuals']);
    }

    /** @return \Iterator<string, array{string}> */
    public static function notIndexed(): \Iterator
    {
        yield 'famille : autre série du même type' => ['SRK25ZZ-W'];
        yield 'aucun document' => ['FDT71VH'];
        yield 'documents exacts sans texte' => ['FDC224KXZME1'];
    }

    #[DataProvider('notIndexed')]
    public function testNoReadableManualGivesNotIndexedWithoutCallingTheAnswerer(string $reference): void
    {
        $answer = $this->ask('Comment nettoyer le filtre ?', $reference);

        $this->assertSame(ManualAnswer::NOT_INDEXED, $answer->text);
        $this->assertSame([], $answer->citations);
        $this->assertSame([], $this->calls);
    }

    /** @return \Iterator<string, array{string, string, array<string, string>}> */
    public static function problems(): \Iterator
    {
        yield 'recevable' => ['Code E5 ?', 'SRK25ZS-W', []];
        yield 'question de 2 caractères' => ['E5', 'SRK25ZS-W', ['question' => AskManuals::QUESTION_TOO_SHORT]];
        yield 'question d\'espaces' => ['   ', 'SRK25ZS-W', ['question' => AskManuals::QUESTION_TOO_SHORT]];
        yield 'question de 301 caractères' => [str_repeat('a', 301), 'SRK25ZS-W', ['question' => AskManuals::QUESTION_TOO_LONG]];
        yield 'question de 300 caractères' => [str_repeat('a', 300), 'SRK25ZS-W', []];
        yield 'référence vide' => ['Code E5 ?', ' ', ['reference' => AskManuals::REFERENCE_REQUIRED]];
        yield 'référence sans rien après le libellé' => ['Code E5 ?', 'Model:', ['reference' => AskManuals::REFERENCE_REQUIRED]];
        yield 'référence de 41 caractères' => ['Code E5 ?', str_repeat('A', 41), ['reference' => AskManuals::REFERENCE_TOO_LONG]];
        yield 'les deux' => ['E5', '', ['question' => AskManuals::QUESTION_TOO_SHORT, 'reference' => AskManuals::REFERENCE_REQUIRED]];
    }

    /** @param array<string, string> $expected */
    #[DataProvider('problems')]
    public function testBoundsOfTheInput(string $question, string $reference, array $expected): void
    {
        $this->assertSame($expected, AskManuals::problems($question, $reference));
    }

    public function testARefusedInputIsNeverAsked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(AskManuals::QUESTION_TOO_SHORT);

        $this->ask('E5', 'SRK25ZS-W');
    }

    public function testAnUnavailableModelIsRaised(): void
    {
        $this->expectException(AiUnavailable::class);

        (new AskManuals(self::library(), $this->answerer(AiUnavailable::because('plafond quotidien atteint'))))('Code E5 ?', 'SRK25ZS-W');
    }

    public function testTheBoundsAreThoseOfAReference(): void
    {
        $this->assertSame(40, AskManuals::REFERENCE_MAX_LENGTH);
    }

    private function ask(string $question, string $reference): ManualAnswer
    {
        return (new AskManuals(self::library(), $this->answerer()))($question, $reference);
    }

    private function answerer(?\Throwable $failure = null): ManualAnswerer
    {
        $calls = &$this->calls;

        return new class($calls, $failure) implements ManualAnswerer {
            /** @param list<array{question: string, manuals: list<string>}> $calls */
            public function __construct(private array &$calls, private readonly ?\Throwable $failure)
            {
            }

            public function answer(string $question, array $manuals): ManualAnswer
            {
                if (null !== $this->failure) {
                    throw $this->failure;
                }
                $this->calls[] = ['question' => $question, 'manuals' => array_map(static fn (Manual $manual): string => $manual->id, $manuals)];

                return ManualAnswer::cited('Réponse de test.', [['manual' => $manuals[0], 'page' => 1]]);
            }
        };
    }

    private static function library(): ManualLibrary
    {
        return new class implements ManualLibrary {
            public function documents(): array
            {
                return [
                    new Manual('srk-zs-w-utilisation', 'Manuel de l\'utilisateur SRK ZS-W (test)', ManualType::Utilisation, ['fr'], 'https://manuels.example/srk.pdf', true,
                        ['SRK20ZS-W', 'SRK25ZS-W', 'SRK35ZS-W'], ['SRK25ZS-W'], 'MHI', text: 'textes/srk-zs-w-utilisation.md', indexedPages: '33-38'),
                    new Manual('srk-zs-w-installation', 'Installation manual SRK ZS-W (test)', ManualType::Installation, ['en'], 'https://manuels.example/srk-inst.pdf', true,
                        ['SRK25ZS-W'], [], 'MHI'),
                    new Manual('srk-zs-wf-technique', 'Technical manual SRK ZS-WF (test)', ManualType::Technique, ['en'], 'https://manuels.example/srk-tech.pdf', true,
                        ['SRK25ZS-WF', 'SRK50ZS-WF'], [], 'MHI', text: 'textes/srk-zs-wf-technique.md', indexedPages: '82'),
                    new Manual('fdc-kxzme1-installation', 'Installation manual FDC224KXZME1 (test)', ManualType::Installation, ['en'], 'https://manuels.example/fdc.pdf', true,
                        ['FDC224KXZME1'], [], 'MHI'),
                ];
            }

            public function products(): array
            {
                return [];
            }
        };
    }
}
