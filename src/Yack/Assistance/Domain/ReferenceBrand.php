<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/**
 * Marque déduite d'une référence par son préfixe (MHI, Yack, RHOSS). Elle est toujours présentée
 * comme « déduite de la référence », jamais comme lue sur la plaque.
 */
final readonly class ReferenceBrand
{
    public const SOURCE = 'deduite';

    /** Préfixes de la forme canonique => marque ; le plus long préfixe qui correspond l'emporte. */
    private const PREFIXES = [
        'MHI' => [
            'SRK', 'SRC', 'SRF', 'SRR', 'SCM', 'SKM', 'FDC', 'FDE', 'FDF', 'FDK', 'FDT', 'FDU', 'HMU', 'HMA', 'HMS', 'HSB',
            'RC-', 'RCH-', 'RCN-', 'SC-', 'LB-', 'T-PSA', 'TC-', 'UM-FL', 'UT-', 'SV-', 'RLD-', 'DIS-', 'DOS-', 'MH-',
            'WF-RAC', 'CW-H', 'Q-TON',
        ],
        'Yack' => [
            'YORA', 'YBM', 'YPRE', 'YPSR', 'YRM', 'YGSA', 'YBOX', 'YMOH', 'YKF', 'YTEL', 'YACK', 'ECOZ', 'HECOZ', 'EZH',
            'LOKYDYP', 'PANDORA', '100GTCMHY',
        ],
        'RHOSS' => ['RHOSS'],
    ];

    private function __construct(
        public string $name,
    ) {
    }

    public static function of(ModelReference $reference): ?self
    {
        $found = null;
        $longest = 0;
        foreach (self::PREFIXES as $brand => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (\strlen($prefix) > $longest && str_starts_with($reference->canonical, $prefix)) {
                    $found = $brand;
                    $longest = \strlen($prefix);
                }
            }
        }

        return null === $found ? null : new self($found);
    }
}
