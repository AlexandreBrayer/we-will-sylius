<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Tests\Yack\Documentation\Application\RecordingDocumentationAnswerer;
use App\Tests\Yack\Documentation\Application\StubPassageRetriever;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Documentation\Application\AskDocumentation;
use App\Yack\Documentation\Domain\Answer;
use App\Yack\Documentation\Domain\Citation;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Infrastructure\Api\DocumentationQuestionInput;
use App\Yack\Documentation\Infrastructure\Api\DocumentationQuestionProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Needs API Platform and Symfony (vendor/): run on the Mac only.
 *
 * @internal
 */
#[CoversClass(DocumentationQuestionProcessor::class)]
final class DocumentationQuestionProcessorTest extends TestCase
{
    public function testAnswerOfTheQuestion(): void
    {
        $answerer = new RecordingDocumentationAnswerer(Answer::cited('25 m.', [new Citation('Notice PAC', 12)], simulated: true));
        $processor = new DocumentationQuestionProcessor(new AskDocumentation(new StubPassageRetriever([new Passage('Notice PAC', 12, 'Liaison : 25 m.')]), $answerer));

        $view = $processor->process($this->input('  Longueur de liaison ? ', 'yk-pae-m08'), new Post());

        $this->assertSame('25 m.', $view->answer);
        $this->assertSame([['source' => 'Notice PAC', 'page' => 12]], $view->sources);
        $this->assertTrue($view->simulated);
    }

    public function testInvalidQuestionIsA422OnItsField(): void
    {
        $processor = new DocumentationQuestionProcessor(new AskDocumentation(new StubPassageRetriever([]), new RecordingDocumentationAnswerer(Answer::notFound())));

        try {
            $processor->process($this->input('Longueur ?', 'YK PAE'), new Post());
            $this->fail('ValidationException attendue');
        } catch (ValidationException $exception) {
            $this->assertSame('productCode', $exception->getConstraintViolationList()->get(0)->getPropertyPath());
        }
    }

    public function testUnavailableModelIsA503WithAClearMessage(): void
    {
        $processor = new DocumentationQuestionProcessor(new AskDocumentation(
            new StubPassageRetriever([new Passage('Notice PAC', 12, 'Liaison : 25 m.')]),
            new RecordingDocumentationAnswerer(AiUnavailable::because('plafond quotidien atteint')),
        ));

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage(DocumentationQuestionProcessor::UNAVAILABLE);

        $processor->process($this->input('Longueur ?', null), new Post());
    }

    private function input(?string $question, ?string $productCode): DocumentationQuestionInput
    {
        $input = new DocumentationQuestionInput();
        $input->question = $question;
        $input->productCode = $productCode;

        return $input;
    }
}
