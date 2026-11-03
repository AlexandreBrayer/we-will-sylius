<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Controller;

use App\Yack\Account\Application\Registration\RegisterCustomer;
use App\Yack\Account\Application\Registration\RegistrationData;
use App\Yack\Account\Application\Registration\RegistrationStep;
use App\Yack\Account\Infrastructure\Form\Registration\AddressStepType;
use App\Yack\Account\Infrastructure\Form\Registration\CompanyStepType;
use App\Yack\Account\Infrastructure\Form\Registration\ContactStepType;
use App\Yack\Account\Infrastructure\Form\Registration\ProfileStepType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Yack sign-up in 4 steps (CDC §3.4): profile → company → contact → addresses.
 *
 * The route keeps Sylius' name "sylius_shop_register" (loaded after the Sylius shop routes,
 * see config/routes/yack.yaml) so every native "Créer un compte" link leads here.
 */
#[Route('/{_locale}', requirements: ['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'])]
final class RegistrationController extends AbstractController
{
    private const SESSION_KEY = 'yack_registration';

    private const DONE_SESSION_KEY = 'yack_registration_done';

    public function __construct(private readonly RegisterCustomer $registerCustomer)
    {
    }

    #[Route('/inscription/{step<profil|societe|contact|adresse>}', name: 'sylius_shop_register', defaults: ['step' => 'profil'], methods: ['GET', 'POST'])]
    public function step(Request $request, string $step): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('sylius_shop_account_dashboard');
        }

        $session = $request->getSession();
        $data = $this->load($session);
        $current = RegistrationStep::from($step);

        // No skipping ahead: back to the first step not validated yet.
        $firstIncomplete = $data->firstIncompleteStep();
        if (null !== $firstIncomplete && $current->position() > $firstIncomplete->position()) {
            return $this->redirectToStep($firstIncomplete);
        }

        $form = $this->createStepForm($current, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && RegistrationStep::Contact === $current && is_string($data->email) && $this->registerCustomer->isEmailTaken($data->email)) {
            $form->get('email')->addError(new FormError('Un compte existe déjà avec cette adresse e-mail. Connectez-vous ou utilisez « Mot de passe oublié ».'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if (RegistrationStep::Contact === $current) {
                $data->passwordHash = $this->registerCustomer->hashPassword((string) $data->plainPassword);
                $data->plainPassword = null;
            }
            if (RegistrationStep::Profile === $current && !$data->isPro()) {
                // ECOM: SIRET optional, re-check the company step if the profile changed.
                $data->completedSteps = [RegistrationStep::Profile->value];
            }
            $data->complete($current);

            $next = $current->next();
            if (null !== $next) {
                $this->save($session, $data);

                return $this->redirectToStep($next);
            }

            $customer = ($this->registerCustomer)($data);
            $session->remove(self::SESSION_KEY);
            $session->set(self::DONE_SESSION_KEY, ['email' => $customer->getEmail(), 'pro' => $data->isPro()]);

            return $this->redirectToRoute('app_yack_register_done');
        }

        $this->save($session, $data);

        return $this->render('shop/yack/account/register/step.html.twig', [
            'form' => $form->createView(),
            'step' => $current,
            'steps' => RegistrationStep::cases(),
            'registration' => $data,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Former Sylius URL (/register): bookmarks and external links land on the Yack sign-up. */
    #[Route('/register', name: 'app_yack_register_legacy', methods: ['GET'])]
    public function legacy(): Response
    {
        return $this->redirectToRoute('sylius_shop_register', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/inscription/merci', name: 'app_yack_register_done', methods: ['GET'])]
    public function done(Request $request): Response
    {
        $done = $request->getSession()->get(self::DONE_SESSION_KEY);
        if (!is_array($done)) {
            return $this->redirectToRoute('sylius_shop_register');
        }

        return $this->render('shop/yack/account/register/done.html.twig', [
            'email' => $done['email'] ?? '',
            'pro' => (bool) ($done['pro'] ?? false),
        ]);
    }

    /** @return FormInterface<mixed> */
    private function createStepForm(RegistrationStep $step, RegistrationData $data): FormInterface
    {
        return match ($step) {
            RegistrationStep::Profile => $this->createForm(ProfileStepType::class, $data),
            RegistrationStep::Company => $this->createForm(CompanyStepType::class, $data, ['company_required' => $data->isPro()]),
            RegistrationStep::Contact => $this->createForm(ContactStepType::class, $data),
            RegistrationStep::Address => $this->createForm(AddressStepType::class, $data),
        };
    }

    private function redirectToStep(RegistrationStep $step): Response
    {
        return $this->redirectToRoute('sylius_shop_register', ['step' => $step->value]);
    }

    private function load(SessionInterface $session): RegistrationData
    {
        $stored = $session->get(self::SESSION_KEY);

        return is_array($stored) ? RegistrationData::fromArray($stored) : new RegistrationData();
    }

    private function save(SessionInterface $session, RegistrationData $data): void
    {
        $session->set(self::SESSION_KEY, $data->toArray());
    }
}
