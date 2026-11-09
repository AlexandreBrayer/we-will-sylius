<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Documentation\Application\AskDocumentation;
use App\Yack\Documentation\Domain\InvalidQuestion;
use App\Yack\Documentation\Domain\Question;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<DocumentationQuestionInput, DocumentationAnswerView>
 */
final readonly class DocumentationQuestionProcessor implements ProcessorInterface
{
    public const UNAVAILABLE = 'L\'assistant documentation est indisponible pour le moment : réessayez dans quelques minutes.';

    public function __construct(private AskDocumentation $askDocumentation)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DocumentationAnswerView
    {
        try {
            $question = Question::ask((string) $data->question, $data->productCode);
        } catch (InvalidQuestion $invalid) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation($invalid->getMessage(), null, [], $data, $invalid->field, null),
            ]));
        }

        try {
            $answer = ($this->askDocumentation)($question);
        } catch (AiUnavailable $unavailable) {
            throw new ServiceUnavailableHttpException(null, self::UNAVAILABLE, $unavailable);
        }

        return DocumentationAnswerView::fromAnswer($answer);
    }
}
