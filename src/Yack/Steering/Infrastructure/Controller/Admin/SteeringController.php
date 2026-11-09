<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Controller\Admin;

use App\Yack\Steering\Application\AfterSalesDeskReport;
use App\Yack\Steering\Application\CustomersDeskReport;
use App\Yack\Steering\Domain\SteeringPage;
use App\Yack\Steering\Infrastructure\Security\SteeringVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office dashboard, tabs « SAV et pièces » and « Clients et encours » (the tab « Pilotage » is the Sylius dashboard
 * itself, /admin/, enriched by hooks). Read-only pages computed on each opening from Sylius and the Yack contexts.
 * Administrators allowed by SteeringVoter only, checked on every request; secured by the Sylius admin firewall.
 * Paths under /admin/yack/tableau-de-bord/: /admin/yack/ like every Yack screen (the Sylius dashboard is /admin/ itself, no prefix
 * to nest under), then the screen they belong to.
 */
#[Route('/%sylius_admin.path_name%/yack/tableau-de-bord')]
final class SteeringController extends AbstractController
{
    #[Route('/sav-et-pieces', name: 'app_yack_admin_steering_after_sales', methods: ['GET'])]
    public function afterSales(Request $request, AfterSalesDeskReport $report): Response
    {
        $this->denyAccessUnlessGranted(SteeringVoter::ATTRIBUTE);

        return $this->render('admin/yack/steering/after_sales.html.twig', ['page' => SteeringPage::AfterSales, 'report' => $report->build($request->query->getString('serie'))]);
    }

    #[Route('/clients-et-encours', name: 'app_yack_admin_steering_customers', methods: ['GET'])]
    public function customers(CustomersDeskReport $report): Response
    {
        $this->denyAccessUnlessGranted(SteeringVoter::ATTRIBUTE);

        return $this->render('admin/yack/steering/customers.html.twig', ['page' => SteeringPage::Customers, 'report' => $report->build()]);
    }
}
