<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Question;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualAnswer;
use App\Yack\Assistance\Domain\ManualAnswerer;
use App\Yack\Documentation\Application\AnswerFromNoticesInterface;
use App\Yack\Documentation\Domain\InvalidQuestion;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\Question;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Adapter de `ManualAnswerer` : le moteur de questions du contexte Documentation (`AnswerFromNoticesInterface` :
 * BM25 lexical, answerer de démonstration ou réel selon YACK_AI_ADAPTER, sources limitées aux passages lus).
 *
 * Chaque manuel devient une `Notice` : titre du document (rendu unique par son id si deux documents portent le même),
 * passages de son texte indexé (`MarkdownManualTexts`) avec leur page du PDF, codes catalogue couverts ; les
 * modèles couverts nomment l'appareil (ils ne comptent pas comme mots de la question). Codes d'erreur accolés
 * (`ManualVocabulary::glueCodes`) dans les passages et la question ; la question est aussi cherchée par ses seuls
 * codes et, quand un manuel lu n'est pas en français, sous sa forme anglaise (`ManualVocabulary::rewordings`).
 *
 * Mise en page (`MarkdownManualTexts`) : les passages d'une grille éclatée ne sont pas lus (le lien entre cellule et
 * ligne est perdu, la réponse serait fausse) ; une source tirée d'un tableau aplati ajoute l'avertissement
 * `ManualAnswer::TABLE_WARNING`.
 */
#[AsAlias(ManualAnswerer::class)]
final readonly class DocumentationManualAnswerer implements ManualAnswerer
{
    public function __construct(
        private MarkdownManualTexts $texts,
        private AnswerFromNoticesInterface $answers,
    ) {
    }

    public function answer(string $question, array $manuals): ManualAnswer
    {
        $notices = [];
        /** @var array<string, Manual> $byTitle */
        $byTitle = [];
        /** @var array<string, true> $tables titre et page des passages de tableau */
        $tables = [];
        $english = false;
        foreach ($manuals as $manual) {
            $read = $this->texts->passages($manual);
            $title = isset($byTitle[$manual->title]) ? $manual->title . ' (' . $manual->id . ')' : $manual->title;

            $passages = [];
            foreach ($read as $passage) {
                if (MarkdownManualTexts::LAYOUT_GRID === $passage['layout']) {
                    continue;
                }
                $passages[] = new Passage($title, $passage['page'], ManualVocabulary::glueCodes($passage['text']), $manual->catalogCodes, $passage['section']);
                if (MarkdownManualTexts::LAYOUT_TABLE === $passage['layout']) {
                    $tables[$title . "\u{0}" . $passage['page']] = true;
                }
            }
            if ([] === $passages) {
                continue;
            }
            $byTitle[$title] = $manual;
            $english = $english || !$manual->isInFrench();
            $notices[] = new Notice($title, implode(' ', array_unique([...$manual->models, ...$manual->catalogCodes])), $manual->catalogCodes, null, $passages);
        }
        if ([] === $notices) {
            return ManualAnswer::notIndexed();
        }

        try {
            $asked = Question::ask(ManualVocabulary::glueCodes($question));
        } catch (InvalidQuestion) {
            $asked = Question::ask($question); // « E 5 » accolé tient en 2 caractères : la question reste telle quelle
        }
        $answer = $this->answers->answer($asked, $notices, ManualVocabulary::rewordings($question, $english));

        $citations = [];
        $fromTable = false;
        foreach ($answer->sources as $source) {
            $manual = $byTitle[$source->source] ?? null;
            if (null !== $manual) {
                $citations[] = ['manual' => $manual, 'page' => $source->page];
                $fromTable = $fromTable || isset($tables[$source->source . "\u{0}" . $source->page]);
            }
        }

        return [] === $citations
            ? ManualAnswer::notFound($answer->simulated)
            : ManualAnswer::cited($answer->text, $citations, $answer->simulated, $fromTable);
    }
}
