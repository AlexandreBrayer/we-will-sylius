<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Controller\Admin;

use App\Yack\Content\Domain\CmsTemplates;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office: CMS templates of the common pages (product page, category page). Created and edited in
 * the SvelteKit builder (FRONT_URL/cms-admin/builder/templates); assigned here, in the product and
 * taxon forms (« Template CMS »), or from the builder. Secured by the Sylius admin firewall.
 */
#[Route('/%sylius_admin.path_name%/yack/contenu/templates')]
final class CmsTemplateAdminController extends AbstractController
{
    public function __construct(private readonly CmsTemplates $templates)
    {
    }

    #[Route('', name: 'app_yack_admin_cms_template_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/yack/cms_template/index.html.twig', [
            'templates' => $this->templates->all(),
        ]);
    }
}
