<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Domain\LanguageModel;

/**
 * Hand-written fake of the LanguageModel port (Common): records the calls, answers what it was given.
 *
 * @internal
 */
final class FakeLanguageModel implements LanguageModel
{
    /** @var list<array{purpose: string, instructions: string, inputs: list<AiInput>, schema: array<string, mixed>}> */
    public array $calls = [];

    /** @param array<string, mixed>|AiUnavailable $answer */
    public function __construct(private readonly array|AiUnavailable $answer)
    {
    }

    public function extract(string $purpose, string $instructions, array $inputs, array $schema): array
    {
        $this->calls[] = ['purpose' => $purpose, 'instructions' => $instructions, 'inputs' => $inputs, 'schema' => $schema];
        if ($this->answer instanceof AiUnavailable) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
