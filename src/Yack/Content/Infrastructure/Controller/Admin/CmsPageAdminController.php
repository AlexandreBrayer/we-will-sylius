<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Controller\Admin;

use App\Yack\Content\Domain\CmsPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office: CMS page list. Pages are created and edited in the SvelteKit builder
 * (front/, /cms-admin/builder on FRONT_URL); this screen links to it. Secured by the Sylius admin firewall.
 */
#[Route('/%sylius_admin.path_name%/yack/contenu/pages')]
final class CmsPageAdminController extends AbstractController
{
    public function __construct(private readonly CmsPages $pages)
    {
    }

    #[Route('', name: 'app_yack_admin_cms_page_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/yack/cms_page/index.html.twig', [
            'pages' => $this->pages->all(),
        ]);
    }
}
