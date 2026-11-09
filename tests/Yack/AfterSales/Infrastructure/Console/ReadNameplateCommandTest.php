<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Console;

use App\Entity\AfterSales\Model;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Infrastructure\Console\ReadNameplateCommand;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\AfterSales\Infrastructure\Nameplate\LanguageModelNameplateReader;
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Catalog\Domain\ProductReference;
use App\Yack\Common\Infrastructure\Ai\JsonValue;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Aucun appel réseau : les réponses des fournisseurs sont enregistrées à la main (ProviderResponses) et passent
 * par les vrais clients de Common, le vrai ExifStripper, le vrai LanguageModelNameplateReader et le vrai cas
 * d'usage IdentifyApplianceFromPhoto.
 *
 * @internal
 */
#[CoversClass(ReadNameplateCommand::class)]
final class ReadNameplateCommandTest extends TestCase
{
    /** Format d'une clé Anthropic, assemblé pour ne pas déclencher le contrôle des secrets du dépôt. */
    private const ANTHROPIC_KEY = 'sk-' . 'ant-api03-TestSecret0123456789';

    private const MISTRAL_KEY = 'mistral-TestSecret9876543210';

    private const PLATE = 'plaque-SRK25ZS-W-FICTIF.png';

    private const MHI = ['readable' => true, 'serial' => 'MH21ZSW0004242', 'reference' => 'SRK25ZS-W', 'model' => 'Mural 2,5 kW', 'brand' => 'MHI'];

    private const PNG_BASE64_START = 'iVBORw0KGgo';

    /** @var list<string> */
    private array $temporary = [];

    /** @var list<array{url: string, headers: list<string>, body: array<string, mixed>}> */
    private array $requests = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->temporary as $file) {
            @unlink($file);
        }
    }

    public function testAnthropicReadingShowsTheWholeExchange(): void
    {
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI, 1523, 87)], products: self::catalogProduct());
        [$width, $height] = self::imageSize(self::plate(self::PLATE));
        [$sentWidth, $sentHeight] = self::sentSize($width, $height);

        $code = $tester->execute(['photo' => self::plate(self::PLATE)]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $code);
        foreach ([
            'Fournisseur anthropic',
            'Modèle demandé modele-x',
            'Modèle qui a répondu modele-anthropic-test',
            \sprintf('%s, image/png, %d x %d px', self::PLATE, $width, $height),
            \sprintf('Image envoyée image/png, %d x %d px', $sentWidth, $sentHeight),
            'sans EXIF, 1600 px au plus',
            'Jetons entrée 1 523, sortie 87',
            'Fin de réponse tool_use',
            '"reference": "SRK25ZS-W"',
            'serial MH21ZSW0004242',
            'serial_number MH21ZSW0004242',
            'simulated false',
            'Modèle reconnu dans le catalogue : Mural SRK25ZS-W (SRK25ZS-W), fiche /produit/srk25zs-w ; documents : Notice.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $output);
        }
        $this->assertMatchesRegularExpression('/Durée de l\'appel \d+ ms/', $output);
        $this->assertStringNotContainsString(self::PNG_BASE64_START, $output);

        // Même chemin que la boutique : consigne, schéma et outil de LanguageModelNameplateReader, outil proposé
        // (tool_choice auto) et exigé par la consigne finale du message, jamais forcé.
        $this->assertCount(1, $this->requests);
        $body = $this->requests[0]['body'];
        $this->assertSame('https://api.anthropic.com/v1/messages', $this->requests[0]['url']);
        $this->assertSame('modele-x', $body['model']);
        $this->assertSame(LanguageModelNameplateReader::INSTRUCTIONS, $body['system']);
        $this->assertSame(LanguageModelNameplateReader::SCHEMA, JsonValue::at($body, 'tools', 0, 'input_schema'));
        $this->assertSame(['type' => 'auto'], $body['tool_choice']);
        $this->assertSame(['type' => 'text', 'text' => AnthropicClient::TOOL_RULE], JsonValue::at($body, 'messages', 0, 'content', 1));
    }

    /**
     * Défaut mesuré le 09/11/2026 avec claude-sonnet-5-5 : un outil forcé (tool_choice tool ou any) est refusé par
     * un 400. Le fournisseur simulé ici répond comme ce modèle ; avec l'ancienne requête, la commande affichait
     * « réponse 400 » et « tool_choice: type "tool" and "any" are not supported for this model. ».
     */
    public function testModelThatRejectsForcedToolReadsThePlate(): void
    {
        $tester = $this->tester(function (): MockResponse {
            $choice = JsonValue::at(end($this->requests), 'body', 'tool_choice', 'type');
            if ('tool' === $choice || 'any' === $choice) {
                return ProviderResponses::anthropicError(400, 'invalid_request_error', 'tool_choice: type "tool" and "any" are not supported for this model.');
            }

            return new MockResponse((string) json_encode([
                'model' => 'claude-sonnet-5-5',
                'content' => [
                    ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
                    ['type' => 'tool_use', 'id' => 'toolu_01Test', 'name' => 'reponse', 'input' => self::MHI],
                ],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 1600, 'output_tokens' => 140],
            ]), ['response_headers' => ['content-type' => 'application/json']]);
        }, ['model' => 'claude-sonnet-5-5'], products: self::catalogProduct());

        $code = $tester->execute(['photo' => self::plate(self::PLATE), '--compare' => true]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringNotContainsString('réponse 400', $output);
        $this->assertStringNotContainsString('not supported', $output);
        $this->assertStringContainsString('Modèle qui a répondu claude-sonnet-5-5', $output);
        $this->assertStringContainsString('Fin de réponse tool_use', $output);
        $this->assertStringContainsString('serial_number MH21ZSW0004242', $output);
        $this->assertMatchesRegularExpression('/N° de série +MH21ZSW0004242 +MH21ZSW0004242/', $output);
        $this->assertMatchesRegularExpression('/Recherche +product +product/', $output);
        $this->assertCount(1, $this->requests);
    }

    public function testAnswerAsJsonTextIsReadLikeTheTool(): void
    {
        $tester = $this->tester([new MockResponse((string) json_encode([
            'content' => [['type' => 'text', 'text' => (string) json_encode(self::MHI)]],
            'stop_reason' => 'end_turn',
        ]))], products: self::catalogProduct());

        $code = $tester->execute(['photo' => self::plate(self::PLATE)]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Fin de réponse end_turn', $output);
        $this->assertStringContainsString('"reference": "SRK25ZS-W"', $output);
        $this->assertStringContainsString('serial_number MH21ZSW0004242', $output);
        $this->assertStringNotContainsString('n\'est pas un objet JSON', $output);
    }

    public function testCompareShowsTheModelAndTheDemoReaderSideBySide(): void
    {
        $tester = $this->tester([ProviderResponses::anthropic(['readable' => true, 'serial' => null, 'reference' => 'SRK25ZS-W', 'model' => null, 'brand' => null])], products: self::catalogProduct());

        $code = $tester->execute(['photo' => self::plate(self::PLATE), '--compare' => true]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Comparaison avec le lecteur de démo', $output);
        $this->assertStringContainsString('Modèle réel (anthropic) Lecteur de démo', $output);
        $this->assertMatchesRegularExpression('/N° de série +null +MH21ZSW0004242/', $output);
        $this->assertMatchesRegularExpression('/Référence +SRK25ZS-W +SRK25ZS-W/', $output);
        $this->assertMatchesRegularExpression('/Recherche +product +product/', $output);
        $this->assertMatchesRegularExpression('/Produit +SRK25ZS-W +SRK25ZS-W/', $output);
        $this->assertStringContainsString('ne regarde pas l\'image', $output);
        $this->assertCount(1, $this->requests, 'Le lecteur de démo ne fait aucun appel.');
    }

    public function testMistralIsChosenByOptionAndJsonOutputIsComplete(): void
    {
        $catalog = $this->createMock(AfterSalesCatalog::class);
        $catalog->method('findModels')->willReturnCallback(static fn (string $serial): array => 'YK24HYD11S000321' === $serial ? [new Model('SC-HYD', 'Hydrolution', 'Hydro')] : []);
        $answer = ['readable' => true, 'serial' => 'YK24-HYD11S 000321', 'reference' => 'FDCW-VNX-A', 'model' => 'Hydrolution', 'brand' => 'YACK'];
        $tester = $this->tester([ProviderResponses::mistral($answer, 1200, 60)], ['adapter' => 'fake'], catalog: $catalog);

        $code = $tester->execute(['photo' => self::plate('plaque-YK24HYD11S000321-FICTIF.png'), '--adapter' => 'mistral', '--json' => true]);
        $report = json_decode($tester->getDisplay(), true);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertIsArray($report);
        $this->assertSame('mistral', $report['provider']);
        $this->assertSame('modele-x', $report['model']);
        $this->assertSame(['input' => 1200, 'output' => 60], $report['tokens']);
        $this->assertSame('stop', $report['stop_reason']);
        $this->assertSame($answer, $report['raw_answer']);
        $this->assertSame($answer, $report['fields']);
        $this->assertSame(['readable' => true, 'serial_number' => 'YK24HYD11S000321', 'reference' => 'FDCW-VNX-A', 'model' => 'Hydrolution', 'brand' => 'YACK', 'simulated' => false], $report['reading']);
        $this->assertSame(['N° de série normalisé : « YK24-HYD11S 000321 » devient « YK24HYD11S000321 ».'], $report['notes']);
        $this->assertSame('found', $report['lookup']['status']);
        $this->assertNull($report['lookup']['message']);
        $this->assertSame(200, $report['http']['status']);
        $this->assertNull($report['http']['hint']);
        $this->assertSame(self::sentSize(...self::imageSize(self::plate('plaque-YK24HYD11S000321-FICTIF.png'))), [$report['sent_image']['width'], $report['sent_image']['height']]);
        $this->assertNull($report['error']);
        $this->assertArrayNotHasKey('compare', $report);
        $this->assertStringNotContainsString(self::PNG_BASE64_START, $tester->getDisplay());

        $this->assertSame('https://api.mistral.ai/v1/chat/completions', $this->requests[0]['url']);
        $this->assertSame(LanguageModelNameplateReader::SCHEMA, JsonValue::at($this->requests[0]['body'], 'response_format', 'json_schema', 'schema'));
        $this->assertSame(LanguageModelNameplateReader::INSTRUCTIONS, JsonValue::at($this->requests[0]['body'], 'messages', 0, 'content'));
    }

    public function testFoundApplianceIsShownInText(): void
    {
        $catalog = $this->createMock(AfterSalesCatalog::class);
        $catalog->method('findModels')->willReturn([new Model('SC-HYD', 'Hydrolution', 'Hydro')]);
        $tester = $this->tester([ProviderResponses::anthropic(['readable' => true, 'serial' => 'YK24HYD11S000321', 'reference' => null, 'model' => null, 'brand' => null])], catalog: $catalog);

        $tester->execute(['photo' => self::plate(self::PLATE)]);

        $this->assertStringContainsString('Appareil du parc : n° YK24HYD11S000321 (même page qu\'une recherche manuelle : /sav/YK24HYD11S000321).', self::flat($tester->getDisplay()));
    }

    public function testJsonCompareCarriesTheDemoReading(): void
    {
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI)]);

        $tester->execute(['photo' => self::plate('plaque-illisible-FICTIF.png'), '--json' => true, '--compare' => true]);
        $report = json_decode($tester->getDisplay(), true);

        $this->assertIsArray($report);
        $this->assertFalse($report['compare']['fake']['reading']['readable']);
        $this->assertTrue($report['compare']['fake']['reading']['simulated']);
        $this->assertSame('unreadable', $report['compare']['fake']['lookup']['status']);
        $this->assertSame('unknown', $report['lookup']['status']);
    }

    public function testLargePhotoIsReducedAndReencodedBeforeSending(): void
    {
        $image = imagecreatetruecolor(3200, 1000);
        $file = $this->temporaryFile('.jpg');
        imagejpeg($image, $file);
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI)]);

        $tester->execute(['photo' => $file, '--json' => true]);
        $report = json_decode($tester->getDisplay(), true);

        $this->assertIsArray($report);
        $this->assertSame(['file' => basename($file), 'bytes' => (int) filesize($file), 'mediaType' => 'image/jpeg', 'width' => 3200, 'height' => 1000], $report['photo']);
        $this->assertSame(1600, $report['sent_image']['width']);
        $this->assertSame(500, $report['sent_image']['height']);
        $this->assertSame('image/jpeg', $report['sent_image']['mediaType']);
    }

    /**
     * @param array<string, mixed> $answer
     * @param list<string>         $expected
     */
    #[DataProvider('provideAnswersTheApplicationCorrects')]
    public function testWhatTheApplicationDropsIsExplained(array $answer, array $expected): void
    {
        $tester = $this->tester([ProviderResponses::anthropic($answer)]);

        $code = $tester->execute(['photo' => self::plate(self::PLATE)]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $code);
        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $output);
        }
    }

    /**
     * @return \Iterator<string, array{array<string, mixed>, list<string>}>
     */
    public static function provideAnswersTheApplicationCorrects(): \Iterator
    {
        yield 'implausible serial' => [
            ['readable' => true, 'serial' => 'ABCDEF', 'reference' => null, 'model' => null, 'brand' => null],
            ['N° de série « ABCDEF » écarté', 'readable vaut true, mais ni n° de série ni référence', 'Plaque illisible : saisissez le numéro de série.'],
        ];
        yield 'model not sure' => [
            ['readable' => false, 'serial' => 'MH21ZSW0004242', 'reference' => 'SRK25ZS-W', 'model' => 'Mural', 'brand' => 'MHI'],
            ['readable ne vaut pas true', 'serial_number null', 'model Mural', 'Plaque illisible'],
        ];
        yield 'reference and brand too long' => [
            ['readable' => true, 'serial' => 'MH21ZSW0004242', 'reference' => str_repeat('A', 41), 'model' => null, 'brand' => str_repeat('B', 61)],
            ['Référence « ' . str_repeat('A', 41) . ' » écartée', 'Marque « ' . str_repeat('B', 61) . ' » écartée', 'Appareil non trouvé : ni dans le parc installé, ni dans le catalogue.'],
        ];
    }

    /** Photo sans le bloc texte des plaques FICTIF (ici la plaque floue) : pas de repli, la boutique répond 503. */
    public function testAnswerInFreeTextIsShownThenUnavailable(): void
    {
        $tester = $this->tester([new MockResponse('{"content":[{"type":"text","text":"Je ne vois pas de plaque."}],"stop_reason":"end_turn"}')]);

        $code = $tester->execute(['photo' => self::plate('plaque-illisible-FICTIF.png')]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('réponse sans contenu structuré', $output);
        $this->assertStringContainsString('Je ne vois pas de plaque.', $output);
        $this->assertStringContainsString('n\'est pas un objet JSON', $output);
        $this->assertStringContainsString('Lecture indisponible : saisissez le numéro de série (la boutique répond 503).', $output);
        $this->assertStringNotContainsString('Repli de la boutique', $output);
        $this->assertStringNotContainsString('HTTP 200', $output);
    }

    /** Plaque FICTIF et modèle indisponible : l'échec du modèle, puis le repli de la boutique, sans second appel. */
    public function testFallbackOfTheShopIsShownForADemoPlate(): void
    {
        $refused = ProviderResponses::anthropicError(400, 'invalid_request_error', 'tool_choice: type "tool" and "any" are not supported for this model');
        $tester = $this->tester([$refused], products: self::catalogProduct());

        $code = $tester->execute(['photo' => self::plate(self::PLATE)]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::FAILURE, $code, 'Le modèle a échoué : la commande le dit, même quand la boutique se replie.');
        $this->assertStringContainsString('Service IA indisponible : réponse 400', $output);
        $this->assertStringContainsString('tool_choice: type "tool" and "any" are not supported', $output);
        $this->assertStringContainsString('Le modèle n\'a rien lu. Pour cette plaque FICTIF, la boutique ne répond pas 503 (« Lecture indisponible : saisissez le numéro de série ») : elle applique le repli ci-dessous.', $output);
        $this->assertStringContainsString('Repli de la boutique (plaque FICTIF de démonstration)', $output);
        $this->assertStringContainsString('lit avec le lecteur de démo, sans autre appel, et marque la réponse « démonstration » (simulated: true)', $output);
        $this->assertStringContainsString('Modèle reconnu dans le catalogue : Mural SRK25ZS-W (SRK25ZS-W), fiche /produit/srk25zs-w', $output);
        $this->assertStringNotContainsString('la boutique répond 503)', $output);
        $this->assertCount(1, $this->requests);

        $json = $this->tester([clone $refused], products: self::catalogProduct());
        $this->assertSame(Command::FAILURE, $json->execute(['photo' => self::plate(self::PLATE), '--json' => true]));
        $report = json_decode($json->getDisplay(), true);
        $this->assertIsArray($report);
        $this->assertSame('unavailable', $report['lookup']['status']);
        $this->assertSame('Service IA indisponible : réponse 400', $report['error']);
        $this->assertSame('demo', $report['fallback']['reader']);
        $this->assertSame('product', $report['fallback']['lookup']['status']);
        $this->assertSame('MH21ZSW0004242', $report['fallback']['lookup']['serial_number']);
        $this->assertTrue($report['fallback']['lookup']['simulated']);
    }

    public function testNoFallbackWhenTheModelAnswers(): void
    {
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI)], products: self::catalogProduct());

        $this->assertSame(Command::SUCCESS, $tester->execute(['photo' => self::plate(self::PLATE), '--json' => true]));
        $report = json_decode($tester->getDisplay(), true);

        $this->assertIsArray($report);
        $this->assertNull($report['fallback']);
        $this->assertSame('product', $report['lookup']['status']);
        $this->assertFalse($report['lookup']['simulated']);
    }

    /** @param list<string> $expected */
    #[DataProvider('provideProviderFailures')]
    public function testProviderFailuresAreExplainedInClear(string $adapter, MockResponse $response, array $expected): void
    {
        $tester = $this->tester([$response], ['adapter' => $adapter]);

        $code = $tester->execute(['photo' => self::plate(self::PLATE)]);
        $output = self::flat($tester->getDisplay());

        $this->assertSame(Command::FAILURE, $code);
        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $output);
        }
        $this->assertStringContainsString('Lecture indisponible', $output);
    }

    /**
     * @return \Iterator<string, array{string, MockResponse, list<string>}>
     */
    public static function provideProviderFailures(): \Iterator
    {
        yield 'anthropic key refused' => ['anthropic', ProviderResponses::anthropicError(401, 'authentication_error', 'invalid x-api-key'), ['réponse 401', 'HTTP 401 : clé refusée', 'Réponse du fournisseur : authentication_error : invalid x-api-key']];
        yield 'anthropic quota' => ['anthropic', ProviderResponses::anthropicError(429, 'rate_limit_error', 'Number of requests has exceeded your rate limit'), ['trop de demandes', 'HTTP 429 : quota ou débit dépassé', 'rate_limit_error']];
        yield 'anthropic refused request' => ['anthropic', ProviderResponses::anthropicError(400, 'invalid_request_error', 'Could not process image'), ['HTTP 400', 'Could not process image']];
        yield 'anthropic overloaded' => ['anthropic', ProviderResponses::anthropicError(529, 'overloaded_error', 'Overloaded'), ['HTTP 529 : fournisseur surchargé']];
        yield 'anthropic timeout' => ['anthropic', new MockResponse('', ['error' => 'Operation timed out after 20001 milliseconds']), ['délai dépassé ou réseau', 'HTTP 0 : aucune réponse : délai de 20 s dépassé', 'Client HTTP : Operation timed out']];
        yield 'mistral key refused' => ['mistral', ProviderResponses::mistralUnauthorized(), ['HTTP 401 : clé refusée', 'Réponse du fournisseur : Unauthorized']];
        yield 'mistral model without vision' => ['mistral', ProviderResponses::mistralInvalid('Model does not support image input'), ['HTTP 400', 'invalid_request_error : Model does not support image input']];
    }

    public function testKeysNeverAppearEvenWhenTheProviderOrTheHttpClientEchoThem(): void
    {
        $echo = 'x-api-key: ' . self::ANTHROPIC_KEY . ' Authorization: Bearer ' . self::MISTRAL_KEY . ' clé ' . self::ANTHROPIC_KEY;
        $failures = [
            'anthropic' => [ProviderResponses::anthropicError(401, 'authentication_error', 'invalid ' . $echo), new MockResponse('', ['error' => 'Échec, en-têtes envoyés : ' . $echo])],
            'mistral' => [new MockResponse((string) json_encode(['message' => $echo, 'detail' => $echo]), ['http_code' => 401]), new MockResponse('', ['error' => $echo])],
        ];

        foreach ($failures as $adapter => $responses) {
            foreach ($responses as $response) {
                foreach ([false, true] as $json) {
                    $tester = $this->tester([clone $response], ['adapter' => $adapter]);
                    $tester->execute(['photo' => self::plate(self::PLATE), '--json' => $json, '--compare' => true]);
                    $display = $tester->getDisplay();

                    $this->assertStringNotContainsString('TestSecret', $display, $adapter . ($json ? ' en JSON' : ' en texte'));
                    $this->assertStringContainsString('[masqué]', $display);
                }
            }
        }

        // Exception du client HTTP levée dès l'envoi, avec les en-têtes dans son message.
        $tester = $this->tester(static fn () => throw new TransportException('En-têtes : ' . $echo));
        $tester->execute(['photo' => self::plate(self::PLATE)]);
        $this->assertStringNotContainsString('TestSecret', $tester->getDisplay());
        $this->assertStringContainsString('délai dépassé ou réseau', self::flat($tester->getDisplay()));

        // Exception inattendue (ici, du catalogue) qui citerait la clé.
        $products = $this->createMock(ProductReferenceReaderInterface::class);
        $products->method('find')->willThrowException(new \RuntimeException('connexion avec ' . self::ANTHROPIC_KEY));
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI)], products: $products);
        $this->assertSame(Command::FAILURE, $tester->execute(['photo' => self::plate(self::PLATE)]));
        $this->assertStringNotContainsString('TestSecret', $tester->getDisplay());
        $this->assertStringContainsString('Erreur inattendue (RuntimeException) : connexion avec [masqué]', self::flat($tester->getDisplay()));
    }

    public function testDailyCapCountsEachReadingAndStopsTheNextOne(): void
    {
        $cache = new ArrayAdapter();
        $guard = new AiUsageGuard($cache, '1');
        $first = $this->tester([ProviderResponses::anthropic(self::MHI)], guard: $guard);
        $this->assertSame(Command::SUCCESS, $first->execute(['photo' => self::plate(self::PLATE)]));

        $second = $this->tester([ProviderResponses::anthropic(self::MHI)], guard: $guard);
        $code = $second->execute(['photo' => self::plate(self::PLATE), '--compare' => true]);
        $output = self::flat($second->getDisplay());

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('plafond quotidien atteint (aucun appel envoyé)', $output);
        $this->assertStringContainsString('Durée de l\'appel aucun appel', $output);
        $this->assertStringContainsString('Lecteur de démo', $output);
        $this->assertStringContainsString('Repli de la boutique (plaque FICTIF de démonstration)', $output);
        $this->assertCount(1, $this->requests);
        $this->assertSame(1, $cache->getItem('yack_ai_calls_' . (new \DateTimeImmutable('today'))->format('Ymd'))->get(), 'Le repli ne compte aucun appel.');
    }

    /**
     * @param array<string, ?string> $env
     * @param array<string, mixed>   $input
     */
    #[DataProvider('provideRefusalsBeforeAnyCall')]
    public function testRefusalsBeforeAnyCall(array $env, array $input, int $code, string $expected): void
    {
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI)], $env);

        $this->assertSame($code, $tester->execute($input + ['photo' => self::plate(self::PLATE)]));
        $this->assertStringContainsString($expected, self::flat($tester->getDisplay()));
        $this->assertSame([], $this->requests);
    }

    /**
     * @return \Iterator<string, array{array<string, ?string>, array<string, mixed>, int, string}>
     */
    public static function provideRefusalsBeforeAnyCall(): \Iterator
    {
        yield 'demo adapter and no option' => [['adapter' => 'fake'], [], Command::INVALID, 'YACK_AI_ADAPTER vaut « fake » ou est vide : précisez --adapter=anthropic ou --adapter=mistral'];
        yield 'empty adapter and no option' => [['adapter' => null], [], Command::INVALID, '--adapter=anthropic ou --adapter=mistral'];
        yield 'unknown adapter' => [[], ['--adapter' => 'openai'], Command::INVALID, '--adapter accepte anthropic ou mistral.'];
        yield 'fake is not a provider' => [[], ['--adapter' => 'fake'], Command::INVALID, '--adapter accepte anthropic ou mistral.'];
        yield 'anthropic key missing' => [['anthropicKey' => ''], [], Command::FAILURE, 'Clé absente : posez ANTHROPIC_API_KEY'];
        yield 'mistral key missing' => [['adapter' => 'mistral', 'mistralKey' => null], [], Command::FAILURE, 'Clé absente : posez MISTRAL_API_KEY'];
        yield 'model missing' => [['model' => ' '], [], Command::FAILURE, 'Modèle absent : posez YACK_AI_MODEL=<identifiant d\'un modèle qui lit les images> dans .env.local, ou passez --model=<identifiant>.'];
        yield 'model of the other provider' => [[], ['--adapter' => 'mistral'], Command::FAILURE, 'YACK_AI_MODEL est un modèle anthropic (YACK_AI_ADAPTER=anthropic) : pour lire avec mistral, passez --model='];
        yield 'other provider without any model' => [['model' => null], ['--adapter' => 'mistral'], Command::FAILURE, 'Modèle absent'];
        yield 'missing photo' => [[], ['photo' => 'var/plaques/absente.jpg'], Command::FAILURE, 'Photo introuvable : var/plaques/absente.jpg'];
        yield 'empty path' => [[], ['photo' => ''], Command::FAILURE, 'Photo introuvable'];
        yield 'json error' => [['anthropicKey' => null], ['--json' => true], Command::FAILURE, '"error": "Clé absente : posez ANTHROPIC_API_KEY'];
    }

    public function testModelOptionWinsOverTheEnvironment(): void
    {
        $tester = $this->tester([ProviderResponses::mistral(self::MHI)]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['photo' => self::plate(self::PLATE), '--adapter' => 'mistral', '--model' => 'modele-vision']));
        $this->assertSame('modele-vision', $this->requests[0]['body']['model']);
        $this->assertContains('Authorization: Bearer ' . self::MISTRAL_KEY, $this->requests[0]['headers']);
    }

    /** @param string $content contenu du fichier */
    #[DataProvider('provideRefusedFiles')]
    public function testFilesAreCheckedLikeAWebUpload(string $content, string $expected): void
    {
        $file = $this->temporaryFile('.jpg');
        file_put_contents($file, $content);
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI)]);

        $this->assertSame(Command::FAILURE, $tester->execute(['photo' => $file]));
        $this->assertStringContainsString($expected, self::flat($tester->getDisplay()));
        $this->assertSame([], $this->requests);
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideRefusedFiles(): \Iterator
    {
        yield 'empty' => ['', 'Le fichier est vide.'];
        yield 'text named .jpg' => ['ceci n\'est pas une photo', 'Format non accepté (JPEG, PNG ou WebP).'];
    }

    public function testTooLargeFileIsRefusedBeforeReading(): void
    {
        $file = $this->temporaryFile('.jpg');
        $handle = fopen($file, 'w');
        $this->assertNotFalse($handle);
        ftruncate($handle, 20 * 1024 * 1024 + 1);
        fclose($handle);
        $tester = $this->tester([ProviderResponses::anthropic(self::MHI)]);

        $this->assertSame(Command::FAILURE, $tester->execute(['photo' => $file]));
        $this->assertStringContainsString('Fichier trop volumineux (20 Mo maximum).', self::flat($tester->getDisplay()));
    }

    /**
     * @param list<MockResponse>|\Closure $responses
     * @param array<string, ?string>      $env
     */
    private function tester(array|\Closure $responses, array $env = [], ?AfterSalesCatalog $catalog = null, ?ProductReferenceReaderInterface $products = null, ?AiUsageGuard $guard = null): CommandTester
    {
        $env += ['adapter' => 'anthropic', 'model' => 'modele-x', 'anthropicKey' => self::ANTHROPIC_KEY, 'mistralKey' => self::MISTRAL_KEY];
        $requests = &$this->requests;
        $calls = 0;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$calls, $responses): MockResponse {
            $requests[] = ['url' => $url, 'headers' => $options['headers'], 'body' => json_decode($options['body'], true)];
            if ($responses instanceof \Closure) {
                return $responses();
            }

            return $responses[$calls++] ?? throw new \LogicException('Appel inattendu au fournisseur.');
        });
        if (null === $catalog) {
            $catalog = $this->createMock(AfterSalesCatalog::class);
            $catalog->method('findModels')->willReturn([]);
        }
        if (null === $products) {
            $products = $this->createMock(ProductReferenceReaderInterface::class);
            $products->method('find')->willReturn(null);
        }

        return new CommandTester(new ReadNameplateCommand(
            $http,
            $guard ?? new AiUsageGuard(new ArrayAdapter()),
            new ExifStripper(),
            new FakeNameplateReader(),
            $catalog,
            $products,
            adapter: $env['adapter'],
            model: $env['model'],
            anthropicKey: $env['anthropicKey'],
            mistralKey: $env['mistralKey'],
        ));
    }

    private function catalogProduct(): ProductReferenceReaderInterface
    {
        $products = $this->createMock(ProductReferenceReaderInterface::class);
        $products->method('find')->willReturnCallback(static fn (string $reference): ?ProductReference => 'SRK25ZS-W' === $reference ? new ProductReference('SRK25ZS-W', 'Mural SRK25ZS-W', 'srk25zs-w', 'MHI', ['Notice']) : null);

        return $products;
    }

    private function temporaryFile(string $suffix): string
    {
        $file = sys_get_temp_dir() . '/yack-plaque-' . bin2hex(random_bytes(6)) . $suffix;
        $this->temporary[] = $file;

        return $file;
    }

    /**
     * Dimensions lues dans le fichier : les plaques de démo peuvent être refaites sans casser ces tests.
     *
     * @return array{int, int}
     */
    private static function imageSize(string $file): array
    {
        $size = getimagesize($file);
        self::assertNotFalse($size);

        return [$size[0], $size[1]];
    }

    /**
     * Dimensions envoyées après la réduction d'ExifStripper (côté le plus long à MAX_SIDE au plus).
     *
     * @return array{int, int}
     */
    private static function sentSize(int $width, int $height): array
    {
        $ratio = min(1, ExifStripper::MAX_SIDE / max($width, $height));

        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    private static function plate(string $name): string
    {
        return \dirname(__DIR__, 5) . '/public/yack/demo/plaques/' . $name;
    }

    /** Sortie sur une ligne, espaces et cadres réduits : les textes longs sont coupés par SymfonyStyle. */
    private static function flat(string $display): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['[ERROR]', '[WARNING]', '[NOTE]', '!', '*'], ' ', $display)));
    }
}
