<?php

declare(strict_types=1);

namespace App\Yack\Installer\Infrastructure\Controller;

use App\Yack\Installer\Domain\Activity;
use App\Yack\Installer\Domain\Installer;
use App\Yack\Installer\Domain\InstallerCriteria;
use App\Yack\Installer\Domain\InstallerDirectory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InstallerController extends AbstractController
{
    public function __construct(private readonly InstallerDirectory $directory)
    {
    }

    #[Route(
        '/{_locale}/trouver-un-installateur',
        name: 'app_yack_installer_index',
        requirements: ['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'],
        methods: ['GET'],
    )]
    public function index(Request $request): Response
    {
        $criteria = InstallerCriteria::fromInput(
            $this->directory,
            $request->query->getString('secteur'),
            $request->query->getString('gamme'),
            $request->query->getString('activite'),
        );

        $installers = $this->directory->search($criteria);

        return $this->render('shop/yack/installer/index.html.twig', [
            'criteria' => $criteria,
            'installers' => $installers,
            'sectors' => $this->directory->sectors(),
            'ranges' => $this->directory->ranges(),
            'activities' => Activity::cases(),
            'markers' => array_map(static fn (Installer $installer): array => [
                'name' => $installer->name,
                'city' => $installer->city,
                'lat' => $installer->latitude,
                'lng' => $installer->longitude,
            ], $installers),
        ]);
    }
}
