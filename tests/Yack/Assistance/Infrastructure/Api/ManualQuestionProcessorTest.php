<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Assistance\Application\AskManuals;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualAnswer;
use App\Yack\Assistance\Domain\ManualAnswerer;
use App\Yack\Assistance\Domain\ManualLibrary;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Infrastructure\Api\AssistanceSwitch;
use App\Yack\Assistance\Infrastructure\Api\ManualAnswerView;
use App\Yack\Assistance\Infrastructure\Api\ManualQuestionInput;
use App\Yack\Assistance\Infrastructure\Api\ManualQuestionProcessor;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Ce test a besoin de Symfony et d'API Platform (vendor/) : PHPUnit seulement. Base et answerer écrits à la main.
 * Le 401 d'un visiteur est porté par l'opération (`security`) : vérifié par appel HTTP sur la pile complète.
 *
 * @internal
 */
#[CoversClass(ManualQuestionProcessor::class)]
final class ManualQuestionProcessorTest extends TestCase
{
    public function testAnswerOfTheQuestionWithItsSources(): void
    {
        $view = $this->process(self::input('Code E16 ?', 'SRK25ZS-W'));

        $this->assertSame('E16 : moteur du ventilateur intérieur.', $view->answer);
        $this->assertSame([['documentId' => 'srk-zs-w-technique', 'title' => 'Technical manual SRK ZS-W (test)', 'page' => 82, 'url' => 'https://manuels.example/srk.pdf']], $view->sources);
        $this->assertTrue($view->simulated);
    }

    public function testAQuestionOfTwoCharactersIsA422OnTheQuestion(): void
    {
        try {
            $this->process(self::input('E5', 'SRK25ZS-W'));
            $this->fail('ValidationException attendue');
        } catch (ValidationException $exception) {
            $this->assertSame('question', $exception->getConstraintViolationList()->get(0)->getPropertyPath());
            $this->assertSame(AskManuals::QUESTION_TOO_SHORT, $exception->getConstraintViolationList()->get(0)->getMessage());
        }
    }

    public function testAMissingReferenceIsA422OnTheReference(): void
    {
        try {
            $this->process(self::input('Code E16 ?', null));
            $this->fail('ValidationException attendue');
        } catch (ValidationException $exception) {
            $this->assertSame('reference', $exception->getConstraintViolationList()->get(0)->getPropertyPath());
        }
    }

    public function testAnUnavailableModelIsA503WithAClearMessage(): void
    {
        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage(ManualQuestionProcessor::UNAVAILABLE);

        $this->process(self::input('Code E16 ?', 'SRK25ZS-W'), failure: AiUnavailable::because('plafond quotidien atteint'));
    }

    public function testASwitchedOffModuleIsA404BeforeAnyCheck(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->process(self::input('E5', null), enabled: false);
    }

    private function process(ManualQuestionInput $input, bool $enabled = true, ?\Throwable $failure = null): ManualAnswerView
    {
        $answerer = new class($failure) implements ManualAnswerer {
            public function __construct(private readonly ?\Throwable $failure)
            {
            }

            public function answer(string $question, array $manuals): ManualAnswer
            {
                if (null !== $this->failure) {
                    throw $this->failure;
                }

                return ManualAnswer::cited('E16 : moteur du ventilateur intérieur.', [['manual' => $manuals[0], 'page' => 82]], simulated: true);
            }
        };
        $library = new class implements ManualLibrary {
            public function documents(): array
            {
                return [new Manual('srk-zs-w-technique', 'Technical manual SRK ZS-W (test)', ManualType::Technique, ['en'], 'https://manuels.example/srk.pdf', true,
                    ['SRK25ZS-W'], [], 'MHI', text: 'textes/srk-zs-w-technique.md', indexedPages: '82')];
            }

            public function products(): array
            {
                return [];
            }
        };

        return (new ManualQuestionProcessor(new AssistanceSwitch($enabled), new AskManuals($library, $answerer)))->process($input, new Post());
    }

    private static function input(?string $question, ?string $reference): ManualQuestionInput
    {
        $input = new ManualQuestionInput();
        $input->question = $question;
        $input->reference = $reference;

        return $input;
    }
}
