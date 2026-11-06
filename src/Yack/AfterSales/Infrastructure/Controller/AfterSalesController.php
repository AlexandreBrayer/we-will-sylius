<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Controller;

use App\Entity\AfterSales\Model;
use App\Yack\Account\Domain\CommercePermission;
use App\Yack\AfterSales\Application\SerialLookup;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Infrastructure\Cart\SyliusPartCartAdder;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * SAV parcours: serial number → models (ServiceCode) → exploded views → spare parts → cart.
 */
#[Route('/{_locale}/sav', requirements: ['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'])]
final class AfterSalesController extends AbstractController
{
    public function __construct(
        private readonly AfterSalesCatalog $catalog,
        private readonly SerialLookup $serialLookup,
        private readonly SyliusPartCartAdder $cartAdder,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_yack_sav_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $lookup = ($this->serialLookup)($request->query->getString('serial'));

        return $this->render('shop/yack/sav/index.html.twig', [
            'query' => $lookup->query,
            'serial' => $lookup->serial,
            'models' => $lookup->models,
            'warranty' => $lookup->warranty,
            'now' => $this->clock->now(),
        ]);
    }

    #[Route('/modele/{serviceCode}', name: 'app_yack_sav_model', methods: ['GET'])]
    public function showModel(string $serviceCode, Request $request): Response
    {
        return $this->render('shop/yack/sav/model.html.twig', [
            'model' => $this->model($serviceCode),
            'serial' => $request->query->get('serial'),
        ]);
    }

    #[Route('/modele/{serviceCode}/vue/{explodedViewId<\d+>}', name: 'app_yack_sav_exploded_view', methods: ['GET'])]
    public function explodedView(string $serviceCode, int $explodedViewId, Request $request): Response
    {
        $model = $this->model($serviceCode);

        return $this->render('shop/yack/sav/exploded_view.html.twig', [
            'model' => $model,
            'exploded_view' => $this->catalog->findExplodedView($model, $explodedViewId)
                ?? throw new NotFoundHttpException(sprintf('Exploded view %d not found for model %s.', $explodedViewId, $serviceCode)),
            'serial' => $request->query->get('serial'),
        ]);
    }

    #[Route('/modele/{serviceCode}/vue/{explodedViewId<\d+>}/panier', name: 'app_yack_sav_add_to_cart', methods: ['POST'])]
    public function addToCart(string $serviceCode, int $explodedViewId, Request $request): Response
    {
        $this->denyAccessUnlessGranted(CommercePermission::PlaceOrder->value);

        if (!$this->isCsrfTokenValid('yack_sav_add_to_cart', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $model = $this->model($serviceCode);
        $explodedView = $this->catalog->findExplodedView($model, $explodedViewId)
            ?? throw new NotFoundHttpException();

        $marker = $request->request->getInt('marker');
        foreach ($explodedView->getItems() as $item) {
            if ($item->getMarker() !== $marker) {
                continue;
            }

            $variant = $item->getPart()->getEnabledVariants()->first();
            if (!$variant instanceof ProductVariantInterface || !$item->getPart()->isEnabled()) {
                $this->addFlash('error', 'Cette pièce n\'est plus commercialisée.');

                break;
            }

            $this->cartAdder->add($variant, $request->request->getInt('quantity', 1));
            $this->addFlash('success', sprintf('Pièce « %s » (repère %d) ajoutée au panier.', $item->getPart()->getName(), $marker));

            break;
        }

        return $this->redirectToRoute('app_yack_sav_exploded_view', [
            'serviceCode' => $model->getServiceCode(),
            'explodedViewId' => $explodedViewId,
            'serial' => $request->query->get('serial'),
            '_locale' => $request->getLocale(),
        ]);
    }

    private function model(string $serviceCode): Model
    {
        return $this->catalog->findModelByServiceCode($serviceCode)
            ?? throw new NotFoundHttpException(sprintf('Model %s not found.', $serviceCode));
    }
}
