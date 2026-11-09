<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Controller\Admin;

use App\Yack\Steering\Application\AfterSalesDeskReport;
use App\Yack\Steering\Application\CustomersDeskReport;
use App\Yack\Steering\Application\PilotageReport;
use App\Yack\Steering\Domain\SteeringPage;
use App\Yack\Steering\Infrastructure\Security\SteeringVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office, « Direction » group: Pilotage, SAV et pièces, Clients et encours. Read-only pages computed on each
 * opening from Sylius and the Yack contexts. Administrators only (SteeringVoter), checked on every request; secured
 * by the Sylius admin firewall.
 */
#[Route('/%sylius_admin.path_name%/yack/direction')]
final class SteeringController extends AbstractController
{
    #[Route('/pilotage', name: 'app_yack_admin_steering_pilotage', methods: ['GET'])]
    public function pilotage(PilotageReport $report): Response
    {
        $this->denyAccessUnlessGranted(SteeringVoter::ATTRIBUTE);

        return $this->render('admin/yack/steering/pilotage.html.twig', ['page' => SteeringPage::Pilotage, 'report' => $report->build()]);
    }

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
