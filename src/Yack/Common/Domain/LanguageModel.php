<?php

declare(strict_types=1);

namespace App\Yack\Common\Domain;

/**
 * Port: structured extraction by a language model. The answer always follows `$schema`
 * (JSON Schema of an object); each context validates it again before using it.
 *
 * Implementations throw AiUnavailable (service down, quota, daily cap reached) or
 * AiMisconfigured (missing key or model), never a transport exception.
 */
interface LanguageModel
{
    /**
     * @param string               $purpose      short code of the calling function (logs and cap), e.g. "sav.nameplate"
     * @param list<AiInput>        $inputs
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function extract(string $purpose, string $instructions, array $inputs, array $schema): array;
}
