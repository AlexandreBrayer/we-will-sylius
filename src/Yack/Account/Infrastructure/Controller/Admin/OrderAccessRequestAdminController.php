<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Controller\Admin;

use App\Entity\Account\OrderAccessRequest;
use App\Yack\Account\Application\OrderAccessDecision;
use App\Yack\Account\Domain\OrderAccessRequests;
use App\Yack\Account\Domain\OrderAccessRequestStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office: PRO "demandes d'ouverture à la commande" (list, detail, accept / refuse).
 * Secured by the Sylius admin firewall (path under /%sylius_admin.path_name%).
 */
#[Route('/%sylius_admin.path_name%/yack/demandes-acces-commande')]
final class OrderAccessRequestAdminController extends AbstractController
{
    public function __construct(
        private readonly OrderAccessRequests $requests,
        private readonly OrderAccessDecision $decision,
    ) {
    }

    #[Route('', name: 'app_yack_admin_order_access_request_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = OrderAccessRequestStatus::tryFrom($request->query->getString('statut'));

        return $this->render('admin/yack/order_access_request/index.html.twig', [
            'requests' => $this->requests->allNewestFirst($status),
            'current_status' => $status,
            'statuses' => OrderAccessRequestStatus::cases(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_yack_admin_order_access_request_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        return $this->render('admin/yack/order_access_request/show.html.twig', [
            'access_request' => $this->find($id),
        ]);
    }

    #[Route('/{id<\d+>}/{decision<accepter|refuser>}', name: 'app_yack_admin_order_access_request_decide', methods: ['POST'])]
    public function decide(int $id, string $decision, Request $request): Response
    {
        $accessRequest = $this->find($id);
        if (!$this->isCsrfTokenValid('yack_order_access_decide_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$accessRequest->isPending()) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');
        } elseif ('accepter' === $decision) {
            $this->decision->accept($accessRequest);
            $this->addFlash('success', sprintf('Demande acceptée : %s passe dans le groupe ECOM (prix et commande en ligne). E-mail « Votre compte est ouvert à la commande » envoyé.', $accessRequest->getCustomer()->getEmail()));
        } else {
            $this->decision->refuse($accessRequest);
            $this->addFlash('info', 'Demande refusée : le client reste en profil PRO.');
        }

        // Decision taken from the customer page (BO > Clients > fiche): go back there.
        if ('customer' === $request->request->getString('_redirect')) {
            return $this->redirectToRoute('sylius_admin_customer_show', ['id' => $accessRequest->getCustomer()->getId()]);
        }

        return $this->redirectToRoute('app_yack_admin_order_access_request_show', ['id' => $id]);
    }

    private function find(int $id): OrderAccessRequest
    {
        return $this->requests->get($id) ?? throw new NotFoundHttpException(sprintf('Order access request #%d not found.', $id));
    }
}
