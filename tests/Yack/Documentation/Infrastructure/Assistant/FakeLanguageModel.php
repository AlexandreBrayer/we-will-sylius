<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Assistant;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\LanguageModel;

/** Hand-written fake of the language model: records the calls, gives the prepared result (or throws). */
final class FakeLanguageModel implements LanguageModel
{
    /** @var list<array{purpose: string, instructions: string, inputs: list<AiInput>, schema: array<string, mixed>}> */
    public array $calls = [];

    /** @param array<string, mixed>|\Throwable $result */
    public function __construct(private readonly array|\Throwable $result)
    {
    }

    public function extract(string $purpose, string $instructions, array $inputs, array $schema): array
    {
        $this->calls[] = ['purpose' => $purpose, 'instructions' => $instructions, 'inputs' => $inputs, 'schema' => $schema];
        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}
