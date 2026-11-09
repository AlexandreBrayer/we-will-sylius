<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Assistance\Application\AskManuals;
use App\Yack\Common\Domain\AiUnavailable;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Module coupé : 404. Saisie hors des bornes de `AskManuals::problems` : 422 sur le champ. Puis `AskManuals` ;
 * modèle de langage indisponible : 503. Le droit (`ROLE_USER`, 401 pour un visiteur) est porté par l'opération.
 *
 * @implements ProcessorInterface<ManualQuestionInput, ManualAnswerView>
 */
final readonly class ManualQuestionProcessor implements ProcessorInterface
{
    public const OPERATION = 'app_yack_api_shop_assistance_question_post';

    public const UNAVAILABLE = 'Les manuels ne peuvent pas répondre pour le moment : réessayez dans quelques minutes.';

    public function __construct(
        private AssistanceSwitch $switch,
        private AskManuals $askManuals,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ManualAnswerView
    {
        $this->switch->assertEnabled();

        $question = $data->question ?? '';
        $reference = $data->reference ?? '';

        $problems = AskManuals::problems($question, $reference);
        if ([] !== $problems) {
            $violations = new ConstraintViolationList();
            foreach ($problems as $field => $message) {
                $violations->add(new ConstraintViolation($message, null, [], $data, $field, null));
            }

            throw new ValidationException($violations);
        }

        try {
            return ManualAnswerView::of(($this->askManuals)($question, $reference));
        } catch (AiUnavailable $unavailable) {
            throw new ServiceUnavailableHttpException(null, self::UNAVAILABLE, $unavailable);
        }
    }
}
