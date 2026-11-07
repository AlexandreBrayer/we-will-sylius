<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/**
 * Un document de la base : manuel, fiche ou brochure officiels d'un fabricant, ouvert par son lien
 * (aucun PDF dans le dépôt). `models` : références couvertes (forme canonique) ; `catalogCodes` :
 * codes du catalogue Yack couverts ; `text` : chemin du texte indexé pour les questions, s'il existe.
 */
final readonly class Manual
{
    public const ID_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

    /**
     * @param list<string> $languages
     * @param list<string> $models
     * @param list<string> $catalogCodes
     *
     * @throws InvalidManual id hors format, url non https, aucun modèle ni code couvert
     */
    public function __construct(
        public string $id,
        public string $title,
        public ManualType $type,
        public array $languages,
        public string $url,
        public bool $official,
        public array $models,
        public array $catalogCodes,
        public ?string $brand = null,
        public ?string $manualNumber = null,
        public ?string $year = null,
        public ?int $pages = null,
        public ?string $text = null,
        public ?string $indexedPages = null,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidManual::document($id, 'identifiant hors format (minuscules, chiffres, tirets)');
        }
        if (!str_starts_with($url, 'https://')) {
            throw InvalidManual::document($id, 'lien non https');
        }
        if ([] === $models && [] === $catalogCodes) {
            throw InvalidManual::document($id, 'aucun modèle ni code catalogue couvert');
        }
    }

    public function hasText(): bool
    {
        return null !== $this->text;
    }

    public function isInFrench(): bool
    {
        return \in_array('fr', $this->languages, true);
    }
}
