<?php

declare(strict_types=1);

namespace App\Yack\Common\Domain;

/**
 * Which adapter the intelligent functions use (YACK_AI_ADAPTER): the deterministic demo
 * adapter by default, a real provider only when asked for explicitly.
 */
enum AiMode: string
{
    case Fake = 'fake';
    case Anthropic = 'anthropic';
    case Mistral = 'mistral';

    public static function fromEnv(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Fake;
    }

    public function isReal(): bool
    {
        return self::Fake !== $this;
    }
}
