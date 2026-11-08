<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/**
 * Marque imprimée sur une plaque, ramenée au nom du catalogue (data/yack/catalog.json) quand elle le désigne sans
 * ambiguïté : même nom à la casse, aux espaces et à la ponctuation près (« YACK » donne « Yack »), ou raison sociale
 * connue (« Mitsubishi Heavy Industries » donne « MHI »). Toute autre marque reste telle que lue : deux noms
 * seulement voisins ne sont jamais rapprochés (« Mitsubishi Electric » n'est pas MHI).
 */
final readonly class PrintedBrand
{
    /** Marques du catalogue, écrites comme lui. */
    public const CATALOG = ['MHI', 'Yack', 'Aerys', 'Hydrolia', 'RHOSS'];

    /** Raisons sociales imprimées (forme comparée : majuscules, sans ponctuation) => marque du catalogue. */
    private const COMPANY_NAMES = [
        'MITSUBISHI HEAVY INDUSTRIES' => 'MHI',
        'MITSUBISHI HEAVY INDUSTRIES LTD' => 'MHI',
        'MITSUBISHI HEAVY INDUSTRIES THERMAL SYSTEMS' => 'MHI',
        'MITSUBISHI HEAVY INDUSTRIES THERMAL SYSTEMS LTD' => 'MHI',
    ];

    public static function name(string $printed): string
    {
        $key = self::key($printed);
        foreach (self::CATALOG as $brand) {
            if (self::key($brand) === $key) {
                return $brand;
            }
        }

        return self::COMPANY_NAMES[$key] ?? $printed;
    }

    private static function key(string $text): string
    {
        return mb_strtoupper(trim((string) preg_replace(['/[.,]/u', '/\s+/u'], ['', ' '], $text)));
    }
}
