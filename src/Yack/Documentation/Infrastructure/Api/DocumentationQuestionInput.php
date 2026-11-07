<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Yack\Documentation\Domain\Question;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/v2/shop/yack/documentation-questions {question, productCode?}: question to the notices
 * (from a product page when productCode is given). Logged-in customers only (PRO and ECOM):
 * 401 for a visitor. 200 {answer, sources: [{source, page}], simulated}; 422 on an empty or too
 * long question; 503 when the language model is unavailable.
 */
#[ApiResource(
    shortName: 'YackDocumentationQuestion',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/documentation-questions',
            status: 200,
            output: DocumentationAnswerView::class,
            normalizationContext: ['groups' => ['documentation_question:item']],
            denormalizationContext: ['groups' => ['documentation_question:write']],
            security: "is_granted('ROLE_USER')",
            processor: DocumentationQuestionProcessor::class,
            name: 'app_yack_api_shop_documentation_question_post',
        ),
    ],
)]
final class DocumentationQuestionInput
{
    #[Groups(['documentation_question:write'])]
    #[Assert\NotBlank(message: 'Écrivez votre question.', normalizer: 'trim')]
    #[Assert\Length(
        min: Question::MIN_LENGTH,
        max: Question::MAX_LENGTH,
        minMessage: 'Votre question doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Votre question ne doit pas dépasser {{ limit }} caractères.',
    )]
    public ?string $question = null;

    #[Groups(['documentation_question:write'])]
    #[Assert\Length(max: 64)]
    public ?string $productCode = null;
}
