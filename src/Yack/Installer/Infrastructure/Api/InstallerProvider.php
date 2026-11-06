<?php

declare(strict_types=1);

namespace App\Yack\Installer\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Installer\Domain\Activity;
use App\Yack\Installer\Domain\Installer;
use App\Yack\Installer\Domain\InstallerCriteria;
use App\Yack\Installer\Domain\InstallerDirectory;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<InstallerSearchView|InstallerFiltersView>
 */
final readonly class InstallerProvider implements ProviderInterface
{
    public const FILTERS_OPERATION = 'app_yack_api_shop_installer_filters';

    public function __construct(private InstallerDirectory $directory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InstallerSearchView|InstallerFiltersView
    {
        if (self::FILTERS_OPERATION === $operation->getName()) {
            return new InstallerFiltersView(
                $this->directory->sectors(),
                $this->directory->ranges(),
                array_map(self::activity(...), Activity::cases()),
            );
        }

        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;
        $criteria = InstallerCriteria::fromInput(
            $this->directory,
            $query?->getString('sector') ?? '',
            $query?->getString('range') ?? '',
            $query?->getString('activity') ?? '',
        );
        $installers = $this->directory->search($criteria);

        return new InstallerSearchView(
            ['sector' => $criteria->sector, 'range' => $criteria->range, 'activity' => $criteria->activity?->value],
            count($installers),
            array_map(static fn (Installer $installer): array => [
                'name' => $installer->name,
                'city' => $installer->city,
                'postcode' => $installer->postcode,
                'sector' => $installer->sector,
                'ranges' => $installer->ranges,
                'activities' => array_map(self::activity(...), $installer->activities),
                'latitude' => $installer->latitude,
                'longitude' => $installer->longitude,
                'phone' => $installer->phone,
                'certifiedRge' => $installer->certifiedRge,
            ], $installers),
        );
    }

    /** @return array{code: string, label: string} */
    private static function activity(Activity $activity): array
    {
        return ['code' => $activity->value, 'label' => $activity->label()];
    }
}
