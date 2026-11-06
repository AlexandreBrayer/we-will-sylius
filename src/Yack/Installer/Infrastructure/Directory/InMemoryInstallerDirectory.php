<?php

declare(strict_types=1);

namespace App\Yack\Installer\Infrastructure\Directory;

use App\Yack\Installer\Domain\Activity;
use App\Yack\Installer\Domain\Installer;
use App\Yack\Installer\Domain\InstallerCriteria;
use App\Yack\Installer\Domain\InstallerDirectory;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * POC adapter: fictitious partner installers. The real adapter would read the Yack CRM/ERP.
 */
#[AsAlias(InstallerDirectory::class)]
final class InMemoryInstallerDirectory implements InstallerDirectory
{
    private const PAC_AIR_EAU = 'PAC air/eau';

    private const PAC_AIR_AIR = 'PAC air/air';

    private const CLIM = 'Climatisation';

    private const ECS = 'Eau chaude sanitaire';

    /** @var list<Installer>|null */
    private ?array $installers = null;

    public function search(InstallerCriteria $criteria): array
    {
        return array_values(array_filter($this->all(), static fn (Installer $installer): bool => $installer->matches($criteria)));
    }

    public function sectors(): array
    {
        $sectors = array_values(array_unique(array_map(static fn (Installer $installer): string => $installer->sector, $this->all())));
        sort($sectors);

        return $sectors;
    }

    public function ranges(): array
    {
        return [self::PAC_AIR_EAU, self::PAC_AIR_AIR, self::CLIM, self::ECS];
    }

    /** @return list<Installer> */
    private function all(): array
    {
        $all = [Activity::Installation, Activity::Maintenance, Activity::Repair];
        $installAndMaintain = [Activity::Installation, Activity::Maintenance];

        return $this->installers ??= [
            new Installer('Provence Clim Services', 'Aix-en-Provence', '13100', 'Sud-Est', [self::PAC_AIR_EAU, self::PAC_AIR_AIR, self::CLIM], $all, 43.5297, 5.4474, '04 00 00 01 01', true),
            new Installer('Thermi-Sud Énergies', 'Marseille', '13008', 'Sud-Est', [self::PAC_AIR_EAU, self::ECS], $installAndMaintain, 43.2547, 5.3866, '04 00 00 01 02', true),
            new Installer('Azur Froid & Confort', 'Nice', '06200', 'Sud-Est', [self::PAC_AIR_AIR, self::CLIM], $all, 43.6866, 7.2290, '04 00 00 01 03'),
            new Installer('Var Énergie Habitat', 'Toulon', '83000', 'Sud-Est', [self::PAC_AIR_EAU, self::PAC_AIR_AIR, self::ECS], [Activity::Installation], 43.1242, 5.9280, '04 00 00 01 04', true),
            new Installer('Vaucluse Thermique', 'Avignon', '84000', 'Sud-Est', [self::PAC_AIR_EAU, self::ECS], [Activity::Maintenance, Activity::Repair], 43.9493, 4.8055, '04 00 00 01 05'),
            new Installer('Lyon Génie Climatique', 'Lyon', '69007', 'Auvergne-Rhône-Alpes', [self::PAC_AIR_EAU, self::PAC_AIR_AIR, self::CLIM, self::ECS], $all, 45.7485, 4.8467, '04 00 00 02 01', true),
            new Installer('Alpes Chauffage Nature', 'Grenoble', '38000', 'Auvergne-Rhône-Alpes', [self::PAC_AIR_EAU, self::ECS], $installAndMaintain, 45.1885, 5.7245, '04 00 00 02 02', true),
            new Installer('Clermont Air Service', 'Clermont-Ferrand', '63000', 'Auvergne-Rhône-Alpes', [self::CLIM, self::PAC_AIR_AIR], [Activity::Repair, Activity::Maintenance], 45.7772, 3.0870, '04 00 00 02 03'),
            new Installer('Occitanie Clim Pro', 'Montpellier', '34000', 'Occitanie', [self::PAC_AIR_AIR, self::CLIM], $all, 43.6108, 3.8767, '04 00 00 03 01', true),
            new Installer('Toulouse Énergies Renouvelables', 'Toulouse', '31000', 'Occitanie', [self::PAC_AIR_EAU, self::ECS], [Activity::Installation], 43.6047, 1.4442, '05 00 00 03 02', true),
            new Installer('Paris Thermie Rénovation', 'Paris', '75011', 'Île-de-France', [self::PAC_AIR_EAU, self::PAC_AIR_AIR, self::ECS], $all, 48.8590, 2.3800, '01 00 00 04 01', true),
            new Installer('Francilienne du Froid', 'Versailles', '78000', 'Île-de-France', [self::CLIM, self::PAC_AIR_AIR], $installAndMaintain, 48.8049, 2.1204, '01 00 00 04 02'),
            new Installer('Bordeaux Confort Thermique', 'Bordeaux', '33000', 'Nouvelle-Aquitaine', [self::PAC_AIR_EAU, self::PAC_AIR_AIR, self::ECS], $all, 44.8378, -0.5792, '05 00 00 05 01', true),
            new Installer('Atlantique Clim', 'La Rochelle', '17000', 'Nouvelle-Aquitaine', [self::CLIM], [Activity::Installation, Activity::Repair], 46.1603, -1.1511, '05 00 00 05 02'),
            new Installer('Nantes Écochauffage', 'Nantes', '44000', 'Grand Ouest', [self::PAC_AIR_EAU, self::ECS], $installAndMaintain, 47.2184, -1.5536, '02 00 00 06 01', true),
            new Installer('Breizh Air & Eau', 'Rennes', '35000', 'Grand Ouest', [self::PAC_AIR_EAU, self::PAC_AIR_AIR, self::CLIM], $all, 48.1173, -1.6778, '02 00 00 06 02'),
        ];
    }
}
