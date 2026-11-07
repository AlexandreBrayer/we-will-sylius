<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Domain;

use App\Yack\Documentation\Domain\InvalidQuestion;
use App\Yack\Documentation\Domain\Question;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Question::class)]
#[CoversClass(InvalidQuestion::class)]
final class QuestionTest extends TestCase
{
    public function testSpacesAreCollapsedAndTheProductCodeUpperCased(): void
    {
        $question = Question::ask("  Longueur   de\nliaison ?  ", ' yk-pae-m08 ');

        $this->assertSame('Longueur de liaison ?', $question->text);
        $this->assertSame('YK-PAE-M08', $question->productCode);
    }

    public function testNoProductCode(): void
    {
        $this->assertNull(Question::ask('Code erreur E5 ?')->productCode);
        $this->assertNull(Question::ask('Code erreur E5 ?', '  ')->productCode);
    }

    public function testLengthLimitsAreIncluded(): void
    {
        $this->assertSame(Question::MIN_LENGTH, mb_strlen(Question::ask('abc')->text));
        $this->assertSame(Question::MAX_LENGTH, mb_strlen(Question::ask(str_repeat('é', Question::MAX_LENGTH))->text));
    }

    #[DataProvider('invalidQuestions')]
    public function testInvalidQuestionIsRefused(string $text, ?string $productCode, string $field, string $message): void
    {
        try {
            Question::ask($text, $productCode);
            $this->assertTrue(false, 'InvalidQuestion attendue');
        } catch (InvalidQuestion $invalid) {
            $this->assertSame($field, $invalid->field);
            $this->assertStringContainsString($message, $invalid->getMessage());
        }
    }

    /** @return \Iterator<string, array{string, ?string, string, string}> */
    public static function invalidQuestions(): \Iterator
    {
        yield 'vide' => ['   ', null, 'question', 'au moins 3 caractères'];
        yield 'trop courte' => ['ab', null, 'question', 'au moins 3 caractères'];
        yield 'trop longue' => [str_repeat('a', Question::MAX_LENGTH + 1), null, 'question', 'dépasser 300 caractères'];
        yield 'code produit invalide' => ['Longueur de liaison ?', 'YK PAE<script>', 'productCode', 'Code produit invalide'];
    }
}
