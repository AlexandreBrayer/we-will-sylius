<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Library;

use App\Yack\Assistance\Domain\InvalidManual;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Infrastructure\Library\JsonManualLibrary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(JsonManualLibrary::class)]
final class JsonManualLibraryTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/manuels-test.json';

    public function testTheFixtureIsLoaded(): void
    {
        $library = new JsonManualLibrary(self::FIXTURE);
        $documents = $library->documents();

        $this->assertSame(['mhi-srk-zs-w-installation-test', 'yack-yorabtm-notice-test'], array_map(static fn (Manual $manual): string => $manual->id, $documents));
        $first = $documents[0];
        $this->assertSame(ManualType::Installation, $first->type);
        $this->assertSame(['en', 'fr'], $first->languages);
        $this->assertSame(['SRC20ZS-W', 'SRK20ZS-W', 'SRK25ZS-W'], $first->models);
        $this->assertSame(['SRK25ZS-W'], $first->catalogCodes);
        $this->assertSame(['MHI', 'TEST-001', '2019', 24], [$first->brand, $first->manualNumber, $first->year, $first->pages]);
        $this->assertSame(['textes/mhi-srk-zs-w-installation-test.md', '12-19'], [$first->text, $first->indexedPages]);
        $this->assertTrue($first->official);
        $this->assertSame('https://manuels.example/srk-zs-w-installation.pdf', $first->url);

        $second = $documents[1];
        $this->assertSame(['2022', null, null, null], [$second->year, $second->pages, $second->manualNumber, $second->text]);
        $this->assertFalse($second->hasText());

        $products = $library->products();
        $this->assertCount(2, $products);
        $this->assertSame(['SRK25ZS-W', 'Mural ZS 2,5 kW (test)', 'MHI'], [$products[0]->code, $products[0]->name, $products[0]->brand]);
        $this->assertSame(['YORABTM200', 'YORABTM270', 'YORABTM450'], $products[1]->family);
        $this->assertSame($documents, $library->documents());
    }

    public function testAMissingFileIsAnEmptyLibrary(): void
    {
        $library = new JsonManualLibrary(__DIR__ . '/absent.json');

        $this->assertSame([], $library->documents());
        $this->assertSame([], $library->products());
    }

    /** @return \Iterator<string, array{array<string, mixed>, string, string}> */
    public static function invalidDocuments(): \Iterator
    {
        yield 'lien http' => [['url' => 'http://manuels.example/a.pdf'], 'mhi-srk-zs-w-installation-test', 'lien non https'];
        yield 'id hors format' => [['id' => 'MHI SRK'], 'MHI SRK', 'identifiant hors format'];
        yield 'type inconnu' => [['type' => 'notice'], 'mhi-srk-zs-w-installation-test', 'type inconnu'];
        yield 'sans titre' => [['title' => ' '], 'mhi-srk-zs-w-installation-test', 'titre absent'];
        yield 'rien de couvert' => [['models' => [], 'catalogCodes' => []], 'mhi-srk-zs-w-installation-test', 'aucun modèle'];
        yield 'id absent' => [['id' => null], '#0', 'identifiant hors format'];
        yield 'id en double' => [['id' => 'yack-yorabtm-notice-test'], 'yack-yorabtm-notice-test', 'identifiant en double'];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidDocuments')]
    public function testAnInvalidDocumentStopsTheLoadingWithItsId(array $override, string $id, string $reason): void
    {
        $payload = json_decode((string) file_get_contents(self::FIXTURE), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload) && \is_array($payload['documents']) && \is_array($payload['documents'][0]));
        $payload['documents'][0] = array_merge($payload['documents'][0], $override);

        $file = self::write((string) json_encode($payload));
        $refused = null;
        try {
            (new JsonManualLibrary($file))->documents();
        } catch (InvalidManual $exception) {
            $refused = $exception;
        } finally {
            unlink($file);
        }

        $this->assertInstanceOf(InvalidManual::class, $refused);
        $this->assertSame($id, $refused->manualId);
        $this->assertStringContainsString($reason, $refused->getMessage());
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function unreadableFiles(): \Iterator
    {
        yield 'JSON invalide' => ['{"documents": [', 'JSON invalide'];
        yield 'sans documents' => ['{"products": []}', 'liste « documents » absente'];
        yield 'produit sans code' => ['{"documents": [], "products": [{"name": "Sans code"}]}', 'produit sans code'];
    }

    #[DataProvider('unreadableFiles')]
    public function testAnUnreadableFileIsRefused(string $content, string $reason): void
    {
        $this->expectException(InvalidManual::class);
        $this->expectExceptionMessage($reason);

        $file = self::write($content);
        try {
            (new JsonManualLibrary($file))->products();
        } finally {
            unlink($file);
        }
    }

    private static function write(string $content): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manuels-');
        file_put_contents($file, $content);

        return $file;
    }
}
