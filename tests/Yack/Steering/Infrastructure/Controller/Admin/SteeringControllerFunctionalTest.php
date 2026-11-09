<?php

declare(strict_types=1);

namespace App\Tests\Yack\Steering\Infrastructure\Controller\Admin;

use App\Entity\User\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Direction » pages on the demo data (make yack-reset): 200 and their key content for admin@yack.test (builder
 * level Libre), 403 for editeur@yack.test (level Charte), group in the menu for the first only. Skipped when the
 * database of the environment has no demo administrators.
 *
 * @internal
 */
#[Group('functional')]
final class SteeringControllerFunctionalTest extends WebTestCase
{
    /** @return \Iterator<string, array{string, list<string>}> */
    public static function pages(): \Iterator
    {
        yield 'pilotage' => ['/admin/yack/direction/pilotage', ['CA HT, 30 jours glissants', 'Alertes', 'CA par gamme', 'Comment les clients paient', 'Option, à chiffrer sur demande']];
        yield 'sav et pièces' => ['/admin/yack/direction/sav-et-pieces?serie=YK25EVO08M001234', ['Retrouver un appareil par numéro de série', 'Pièces les plus commandées', 'Garanties à échéance', '/sav/YK25EVO08M001234', 'dates simulées']];
        yield 'clients et encours' => ['/admin/yack/direction/clients-et-encours', ['Échéances non réglées par client', "Balance âgée de l'échu", 'Demandes PRO à valider', "Plafond par client : à brancher sur l'outil comptable de Yack."]];
    }

    /** @param list<string> $content */
    #[DataProvider('pages')]
    public function testTheAdministratorOpensThePage(string $path, array $content): void
    {
        $client = $this->clientAs('admin@yack.test');
        $client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        foreach ($content as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('href="#navbar-yack_direction"', $html);
    }

    /** @param list<string> $content */
    #[DataProvider('pages')]
    public function testTheEditorIsRefused(string $path, array $content): void
    {
        $client = $this->clientAs('editeur@yack.test');
        $client->request('GET', $path);

        $this->assertResponseStatusCodeSame(403);
        $this->assertStringNotContainsString('href="#navbar-yack_direction"', (string) $client->getResponse()->getContent());
    }

    private function clientAs(string $email): KernelBrowser
    {
        $client = static::createClient();
        try {
            $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(AdminUser::class)->findOneBy(['email' => $email]);
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Base de démonstration indisponible : '.$exception->getMessage());
        }
        if (!$user instanceof AdminUser) {
            $this->markTestSkipped(sprintf('%s absent : charger les fixtures (make yack-reset).', $email));
        }
        $client->loginUser($user, 'admin');

        return $client;
    }
}
