<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Console;

/**
 * Masque les secrets dans tout ce que la commande de lecture de plaque affiche : la valeur des clés connues
 * (ANTHROPIC_API_KEY, MISTRAL_API_KEY), les en-têtes d'authentification (`x-api-key`, `Authorization`,
 * `Bearer ...`) et toute chaîne au format d'une clé Anthropic, même si elle vient d'un message d'erreur du
 * fournisseur ou du client HTTP.
 */
final readonly class SecretMasker
{
    public const MASK = '[masqué]';

    /** En dessous, une « clé » n'en est pas une : la remplacer partout rendrait la sortie illisible. */
    private const MIN_SECRET_LENGTH = 6;

    private const PATTERNS = [
        '/((?:x-api-key|api-key|authorization|proxy-authorization)\s*["\']?\s*[:=]\s*["\']?)(?:Bearer\s+)?[^\s"\',;]+/i',
        '/\bBearer\s+[^\s"\',;]+/i',
        // Classe [t] : la règle reste lisible sans que le contrôle des secrets du dépôt la prenne pour une clé.
        '/\bsk-an[t]-[A-Za-z0-9_\-]+/',
    ];

    private const REPLACEMENTS = [
        '${1}' . self::MASK,
        'Bearer ' . self::MASK,
        self::MASK,
    ];

    /** @var list<string> */
    private array $secrets;

    /** @param list<?string> $secrets */
    public function __construct(array $secrets)
    {
        $kept = [];
        foreach ($secrets as $secret) {
            $secret = trim((string) $secret);
            if (\strlen($secret) >= self::MIN_SECRET_LENGTH) {
                $kept[] = $secret;
            }
        }
        // La plus longue d'abord : une clé qui en contient une autre est masquée en entier.
        usort($kept, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));
        $this->secrets = $kept;
    }

    public function mask(string $text): string
    {
        foreach ($this->secrets as $secret) {
            $text = str_replace($secret, self::MASK, $text);
        }

        return (string) preg_replace(self::PATTERNS, self::REPLACEMENTS, $text);
    }

    /** Même masquage dans une valeur JSON décodée (chaînes et clés de tableaux, à toute profondeur). */
    public function maskValue(mixed $value): mixed
    {
        if (\is_string($value)) {
            return $this->mask($value);
        }
        if (!\is_array($value)) {
            return $value;
        }

        $masked = [];
        foreach ($value as $key => $item) {
            $masked[\is_string($key) ? $this->mask($key) : $key] = $this->maskValue($item);
        }

        return $masked;
    }
}
