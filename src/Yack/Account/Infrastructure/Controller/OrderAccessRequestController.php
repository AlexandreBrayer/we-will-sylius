<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Controller;

use App\Entity\Customer\Customer;
use App\Yack\Account\Application\OrderAccessRequestData;
use App\Yack\Account\Domain\CommercePermission;
use App\Yack\Account\Domain\CustomerProfile;
use App\Yack\Account\Domain\OrderAccessRequests;
use App\Yack\Account\Infrastructure\Form\OrderAccessRequestType;
use App\Yack\Account\Infrastructure\Security\CustomerProfileResolver;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRO customer: "Commander en ligne" → request to open online ordering on the account.
 */
#[Route('/{_locale}/compte/acces-commande', requirements: ['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'])]
final class OrderAccessRequestController extends AbstractController
{
    public function __construct(
        private readonly OrderAccessRequests $requests,
        private readonly CustomerProfileResolver $profileResolver,
    ) {
    }

    #[Route('', name: 'app_yack_order_access_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (CustomerProfile::Ecom === $this->profileResolver->current()) {
            $this->addFlash('info', 'Votre compte est ouvert à la commande en ligne : prix et panier sont disponibles.');

            return $this->redirectToRoute('sylius_shop_account_dashboard', ['_locale' => $request->getLocale()]);
        }

        $this->denyAccessUnlessGranted(CommercePermission::RequestOrderAccess->value);
        $customer = $this->customer();

        $latest = $this->requests->latestFor($customer);
        if (null !== $latest && $latest->isPending()) {
            return $this->render('shop/yack/account/order_access_request.html.twig', [
                'pending_request' => $latest,
                'form' => null,
                'refused_request' => null,
            ]);
        }

        $form = $this->createForm(OrderAccessRequestType::class, OrderAccessRequestData::fromCustomer($customer));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert($data instanceof OrderAccessRequestData);
            $this->requests->add($data->toRequest($customer));
            $this->addFlash('success', 'Votre demande d\'ouverture à la commande en ligne a bien été envoyée. Votre agence Yack revient vers vous sous 48 h ouvrées.');

            return $this->redirectToRoute('app_yack_order_access_request', ['_locale' => $request->getLocale()]);
        }

        return $this->render('shop/yack/account/order_access_request.html.twig', [
            'pending_request' => null,
            'form' => $form,
            'refused_request' => $latest,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function customer(): Customer
    {
        $user = $this->getUser();
        $customer = $user instanceof ShopUserInterface ? $user->getCustomer() : null;
        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException();
        }

        return $customer;
    }
}
