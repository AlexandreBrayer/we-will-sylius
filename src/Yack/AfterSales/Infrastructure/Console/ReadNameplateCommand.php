<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Console;

use App\Yack\AfterSales\Application\ApplianceIdentification;
use App\Yack\AfterSales\Application\IdentificationStatus;
use App\Yack\AfterSales\Application\IdentifyApplianceFromPhoto;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\AfterSales\Domain\SerialNumberFormat;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\AfterSales\Infrastructure\Nameplate\LanguageModelNameplateReader;
use App\Yack\AfterSales\Infrastructure\Nameplate\NameplateReaderSelector;
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Common\Domain\AiMode;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use App\Yack\Common\Infrastructure\Anthropic\AnthropicClient;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use App\Yack\Common\Infrastructure\Mistral\MistralClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mode debug de « La pièce par une photo » : lit une photo de plaque locale avec le vrai modèle et montre tout
 * l'échange, pour comprendre ce que le modèle voit et ce que l'application en fait.
 *
 * Même chemin que la boutique : type lu sur le contenu et taille maximale (NameplatePhoto), réencodage sans
 * EXIF et réduction (ExifStripper), consigne et schéma de LanguageModelNameplateReader, client du fournisseur
 * de Common (plafond quotidien YACK_AI_DAILY_CAP compté, délai de 20 s), puis IdentifyApplianceFromPhoto pour
 * la recherche (parc installé, puis catalogue). Seule différence : le fournisseur est choisi ici
 * (--adapter, sinon YACK_AI_ADAPTER), même quand l'application tourne avec le lecteur de démo.
 *
 * La lecture passe par NameplateReaderSelector, comme en boutique : quand le modèle est indisponible et que la
 * photo est une plaque FICTIF de démonstration, la commande montre l'échec du modèle puis le repli de la boutique
 * (lecture par le lecteur de démo, marquée démonstration), sans second appel ; le code de sortie reste un échec.
 *
 * Jamais affichés ni journalisés : la clé (masquée partout, en-têtes d'authentification compris) et l'image.
 */
#[AsCommand(
    name: 'app:yack:nameplate:read',
    description: 'Lit une photo de plaque signalétique avec le vrai modèle et montre l\'échange (mode debug)',
)]
final class ReadNameplateCommand extends Command
{
    private const NO_VALUE = 'null';

    private readonly SecretMasker $masker;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiUsageGuard $guard,
        private readonly ExifStripper $exifStripper,
        private readonly FakeNameplateReader $fakeReader,
        private readonly AfterSalesCatalog $catalog,
        private readonly ProductReferenceReaderInterface $products,
        #[Autowire(env: 'default::YACK_AI_ADAPTER')]
        private readonly ?string $adapter = null,
        #[Autowire(env: 'default::YACK_AI_MODEL')]
        private readonly ?string $model = null,
        #[Autowire(env: 'default::ANTHROPIC_API_KEY')]
        private readonly ?string $anthropicKey = null,
        #[Autowire(env: 'default::MISTRAL_API_KEY')]
        private readonly ?string $mistralKey = null,
        #[Autowire(env: 'default::MISTRAL_BASE_URL')]
        private readonly ?string $mistralBaseUrl = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->masker = new SecretMasker([$anthropicKey, $mistralKey]);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('photo', InputArgument::REQUIRED, 'Photo de la plaque (JPEG, PNG ou WebP, 20 Mo au plus), chemin relatif à la racine du projet')
            ->addOption('adapter', null, InputOption::VALUE_REQUIRED, 'Fournisseur : anthropic ou mistral (défaut : YACK_AI_ADAPTER)')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Modèle du fournisseur (défaut : YACK_AI_MODEL)')
            ->addOption('compare', null, InputOption::VALUE_NONE, 'Fait aussi lire la photo par le lecteur de démo et met les deux résultats côte à côte')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Sortie en JSON')
            ->setHelp(<<<'TXT'
                Exemple : <info>bin/console app:yack:nameplate:read public/yack/demo/plaques/plaque-SRK25ZS-W-FICTIF.png --compare</info>

                Chaque lecture est un appel réel au fournisseur, compté dans le plafond quotidien (YACK_AI_DAILY_CAP).
                La clé du fournisseur (ANTHROPIC_API_KEY ou MISTRAL_API_KEY) et le modèle (YACK_AI_MODEL) se posent dans .env.local.
                TXT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $json = true === $input->getOption('json');

        try {
            return $this->readPhoto($input, $io, $json);
        } catch (\Throwable $error) {
            // Aucune exception ne remonte à la console : sa trace pourrait porter des en-têtes ou des arguments.
            return $this->fail($io, $json, sprintf('Erreur inattendue (%s) : %s', (new \ReflectionClass($error))->getShortName(), $error->getMessage()));
        }
    }

    private function readPhoto(InputInterface $input, SymfonyStyle $io, bool $json): int
    {
        $provider = $this->provider($input->getOption('adapter'));
        if (\is_string($provider)) {
            return $this->fail($io, $json, $provider, Command::INVALID);
        }

        $modelOption = $input->getOption('model');
        $model = self::filled(\is_string($modelOption) ? $modelOption : null) ?? $this->environmentModel($provider);
        if (null === $model) {
            return $this->fail($io, $json, $this->modelProblem($provider));
        }

        $keyVariable = AiMode::Anthropic === $provider ? 'ANTHROPIC_API_KEY' : 'MISTRAL_API_KEY';
        $key = self::filled(AiMode::Anthropic === $provider ? $this->anthropicKey : $this->mistralKey);
        if (null === $key) {
            return $this->fail($io, $json, sprintf('Clé absente : posez %s=<votre clé> dans .env.local (à la racine du projet, jamais commité), puis relancez. Aucun appel n\'est parti.', $keyVariable));
        }

        $path = $input->getArgument('photo');
        $photo = $this->photo(\is_string($path) ? $path : '');
        if (\is_string($photo)) {
            return $this->fail($io, $json, $photo);
        }
        [$nameplate, $file] = $photo;

        $http = new RecordingHttpClient($this->httpClient);
        $real = new RecordingNameplateReader(new LanguageModelNameplateReader($this->client($provider, $model, $key, $http), $this->exifStripper));
        // Le sélecteur de la boutique, sur le lecteur réel enregistreur : un seul appel donne la lecture du modèle
        // et, s'il est indisponible, le repli de la boutique pour une plaque FICTIF.
        $shop = new NameplateReaderSelector($this->fakeReader, $real, $provider->value, $this->logger);
        $shopIdentification = (new IdentifyApplianceFromPhoto($shop, $this->catalog, $this->products))($nameplate);
        $exchange = ProviderExchange::fromRecording($provider, $model, $http);

        // Ce que le modèle seul donne, et le repli de la boutique quand il a joué.
        $identification = null === $real->unavailable() ? $shopIdentification : ApplianceIdentification::unavailable();
        $fallback = null !== $real->unavailable() && IdentificationStatus::Unavailable !== $shopIdentification->status ? $shopIdentification : null;

        $compare = null;
        if (true === $input->getOption('compare')) {
            $fake = new RecordingNameplateReader($this->fakeReader);
            $compare = [$fake, (new IdentifyApplianceFromPhoto($fake, $this->catalog, $this->products))($nameplate)];
        }

        $report = $this->report($file, $exchange, $real, $identification, $fallback, $compare);
        if ($json) {
            $io->writeln(self::json($this->masker->maskValue($report)));
        } else {
            $this->render($io, $file, $exchange, $real, $identification, $fallback, $compare);
        }

        return null === $real->unavailable() ? Command::SUCCESS : Command::FAILURE;
    }

    /** Fournisseur demandé, ou message d'erreur. */
    private function provider(mixed $option): AiMode|string
    {
        if (\is_string($option) && '' !== trim($option)) {
            $mode = AiMode::tryFrom(strtolower(trim($option)));

            return null !== $mode && $mode->isReal() ? $mode : '--adapter accepte anthropic ou mistral.';
        }

        $mode = AiMode::fromEnv($this->adapter);

        return $mode->isReal()
            ? $mode
            : 'YACK_AI_ADAPTER vaut « fake » ou est vide : précisez --adapter=anthropic ou --adapter=mistral (ou posez YACK_AI_ADAPTER dans .env.local).';
    }

    /** YACK_AI_MODEL, seulement s'il est celui du fournisseur demandé (même fournisseur que YACK_AI_ADAPTER, ou YACK_AI_ADAPTER fake). */
    private function environmentModel(AiMode $provider): ?string
    {
        $configured = AiMode::fromEnv($this->adapter);

        return $configured->isReal() && $configured !== $provider ? null : self::filled($this->model);
    }

    private function modelProblem(AiMode $provider): string
    {
        $configured = AiMode::fromEnv($this->adapter);
        if ($configured->isReal() && $configured !== $provider && null !== self::filled($this->model)) {
            return sprintf('YACK_AI_MODEL est un modèle %s (YACK_AI_ADAPTER=%s) : pour lire avec %s, passez --model=<identifiant d\'un modèle %s qui lit les images>.', $configured->value, $configured->value, $provider->value, $provider->value);
        }

        return 'Modèle absent : posez YACK_AI_MODEL=<identifiant d\'un modèle qui lit les images> dans .env.local, ou passez --model=<identifiant>.';
    }

    /**
     * Photo contrôlée comme un envoi web (taille, type lu sur le contenu), ou message d'erreur.
     *
     * @return array{0: NameplatePhoto, 1: array{file: string, bytes: int, mediaType: string, width: ?int, height: ?int}}|string
     */
    private function photo(string $path): array|string
    {
        if ('' === trim($path) || !is_file($path)) {
            return sprintf('Photo introuvable : %s (chemin relatif à la racine du projet, vu depuis le conteneur php).', $path);
        }
        if (!is_readable($path)) {
            return sprintf('Photo illisible (droits) : %s.', $path);
        }
        if ((int) filesize($path) > NameplatePhoto::MAX_BYTES) {
            return NameplatePhotoRefused::tooLarge()->getMessage() . '.';
        }

        $bytes = (string) file_get_contents($path);
        $mediaType = '' === $bytes ? '' : (string) (new \finfo(\FILEINFO_MIME_TYPE))->buffer($bytes);

        try {
            $photo = NameplatePhoto::of($bytes, $mediaType);
        } catch (NameplatePhotoRefused $refused) {
            return $refused->getMessage();
        }
        $size = @getimagesizefromstring($bytes);

        return [$photo, [
            'file' => basename($path),
            'bytes' => \strlen($bytes),
            'mediaType' => $mediaType,
            'width' => false === $size ? null : $size[0],
            'height' => false === $size ? null : $size[1],
        ]];
    }

    /** Le client réel du fournisseur, construit comme celui de l'application, sur le client HTTP enregistreur. */
    private function client(AiMode $provider, string $model, string $key, RecordingHttpClient $http): LanguageModel
    {
        return AiMode::Anthropic === $provider
            ? new AnthropicClient($http, $this->guard, $key, $model, $this->logger)
            : new MistralClient($http, $this->guard, $key, $model, baseUrl: $this->mistralBaseUrl, logger: $this->logger);
    }

    /**
     * Tout ce qui est montré, en données (la sortie JSON, et la source de la sortie texte).
     *
     * @param array{file: string, bytes: int, mediaType: string, width: ?int, height: ?int} $file
     * @param array{0: RecordingNameplateReader, 1: ApplianceIdentification}|null         $compare
     *
     * @return array<string, mixed>
     */
    private function report(array $file, ProviderExchange $exchange, RecordingNameplateReader $real, ApplianceIdentification $identification, ?ApplianceIdentification $fallback, ?array $compare): array
    {
        $fields = $exchange->fields();
        $unavailable = $real->unavailable();
        $report = [
            'provider' => $exchange->provider->value,
            'model' => $exchange->model,
            'photo' => $file,
            'sent_image' => $exchange->image,
            'http' => [
                'sent' => $exchange->sent,
                'status' => $exchange->httpStatus,
                'duration_ms' => $exchange->durationMs,
                'provider_error' => $exchange->providerError,
                'transport_error' => $exchange->transportError,
                'hint' => $exchange->sent && 200 !== $exchange->httpStatus ? ProviderExchange::statusHint($exchange->httpStatus) : null,
            ],
            'reading_duration_ms' => $real->durationMs(),
            'tokens' => ['input' => $exchange->inputTokens, 'output' => $exchange->outputTokens],
            'stop_reason' => $exchange->stopReason,
            'answered_model' => $exchange->answeredModel,
            'raw_answer' => $exchange->answer,
            'fields' => [] === $fields ? null : $fields,
            'reading' => self::readingData($real->reading()),
            'notes' => self::notes($fields, $real->reading()),
            'lookup' => self::lookupData($identification),
            'error' => null === $unavailable ? null : $unavailable->getMessage() . ($exchange->sent ? '' : ' (aucun appel envoyé)'),
            'fallback' => null === $fallback ? null : ['reader' => 'demo', 'lookup' => self::lookupData($fallback)],
        ];
        if (null !== $compare) {
            $report['compare'] = ['fake' => ['reading' => self::readingData($compare[0]->reading()), 'lookup' => self::lookupData($compare[1])]];
        }

        return $report;
    }

    /**
     * @param array{file: string, bytes: int, mediaType: string, width: ?int, height: ?int} $file
     * @param array{0: RecordingNameplateReader, 1: ApplianceIdentification}|null         $compare
     */
    private function render(SymfonyStyle $io, array $file, ProviderExchange $exchange, RecordingNameplateReader $real, ApplianceIdentification $identification, ?ApplianceIdentification $fallback, ?array $compare): void
    {
        $io->title('Lecture de plaque par le vrai modèle (mode debug)');
        $image = $exchange->image;
        $io->definitionList(
            ['Fournisseur' => $exchange->provider->value],
            ['Modèle demandé' => $this->masker->mask($exchange->model)],
            ['Modèle qui a répondu' => $this->masker->mask($exchange->answeredModel ?? 'non renvoyé')],
            ['Photo' => $this->masker->mask(sprintf('%s, %s, %s, %s', $file['file'], $file['mediaType'], self::dimensions($file['width'], $file['height']), self::bytes($file['bytes'])))],
            ['Image envoyée' => null === $image ? 'aucune' : sprintf('%s, %s, %s (sans EXIF, %d px au plus)', $image['mediaType'], self::dimensions($image['width'], $image['height']), self::bytes($image['bytes']), ExifStripper::MAX_SIDE)],
            ['Durée de l\'appel' => null === $exchange->durationMs ? 'aucun appel' : self::milliseconds($exchange->durationMs)],
            ['Durée de la lecture' => self::milliseconds($real->durationMs() ?? 0) . ' (réencodage et appel)'],
            ['Jetons' => null === $exchange->inputTokens && null === $exchange->outputTokens ? 'non renvoyés par l\'API' : sprintf('entrée %s, sortie %s', self::number($exchange->inputTokens), self::number($exchange->outputTokens))],
            ['Fin de réponse' => $this->masker->mask($exchange->stopReason ?? 'non renvoyée')],
        );

        $unavailable = $real->unavailable();
        if (null !== $unavailable) {
            $details = [$unavailable->getMessage() . ($exchange->sent ? '' : ' (aucun appel envoyé)')];
            if ($exchange->sent && 200 !== $exchange->httpStatus) {
                $details[] = sprintf('HTTP %d : %s', $exchange->httpStatus, ProviderExchange::statusHint($exchange->httpStatus));
            }
            if (null !== $exchange->providerError) {
                $details[] = 'Réponse du fournisseur : ' . $exchange->providerError;
            }
            if (null !== $exchange->transportError) {
                $details[] = 'Client HTTP : ' . $exchange->transportError;
            }
            $io->error(array_map($this->masker->mask(...), $details));
        }

        if ($exchange->sent && 200 === $exchange->httpStatus) {
            $io->section('Réponse brute du modèle');
            $io->writeln($this->masker->mask(\is_string($exchange->answer) ? $exchange->answer : self::json($exchange->answer)));

            $io->section('Champs lus par le modèle (avant contrôle)');
            $fields = $exchange->fields();
            if ([] === $fields) {
                $io->warning('La réponse n\'est pas un objet JSON : aucun champ lu.');
            } else {
                $io->table(['Champ', 'Valeur rendue'], $this->rows($fields));
            }
        }

        $reading = self::readingData($real->reading());
        if (null !== $reading) {
            $io->section('Ce que l\'application en garde (NameplateReading)');
            $io->table(['Champ', 'Valeur gardée'], $this->rows($reading));
            $notes = self::notes($exchange->fields(), $real->reading());
            if ([] !== $notes) {
                $io->listing(array_map($this->masker->mask(...), $notes));
            }
        }

        $io->section('Ce que la recherche donne (IdentifyApplianceFromPhoto)');
        $io->writeln($this->masker->mask(null === $fallback ? self::lookupLine($identification) : 'Le modèle n\'a rien lu. Pour cette plaque FICTIF, la boutique ne répond pas 503 (« ' . ApplianceIdentification::UNAVAILABLE_MESSAGE . ' ») : elle applique le repli ci-dessous.'));

        if (null !== $fallback) {
            $io->section('Repli de la boutique (plaque FICTIF de démonstration)');
            $io->writeln('Le modèle est indisponible et la photo est, à l\'octet près, une des plaques FICTIF de public/yack/demo/plaques/ : la boutique la lit avec le lecteur de démo, sans autre appel, et marque la réponse « démonstration » (simulated: true).');
            $io->writeln($this->masker->mask(self::lookupLine($fallback)));
        }

        if (null !== $compare) {
            $this->renderCompare($io, $exchange, $real, $identification, $compare[0], $compare[1]);
        }
    }

    private function renderCompare(SymfonyStyle $io, ProviderExchange $exchange, RecordingNameplateReader $real, ApplianceIdentification $identification, RecordingNameplateReader $fake, ApplianceIdentification $fakeIdentification): void
    {
        $io->section('Comparaison avec le lecteur de démo (FakeNameplateReader)');
        $reading = self::readingData($real->reading()) ?? [];
        $fakeReading = self::readingData($fake->reading()) ?? [];
        $rows = [];
        foreach ([
            'readable' => 'Lisible',
            'serial_number' => 'N° de série',
            'reference' => 'Référence',
            'model' => 'Désignation',
            'brand' => 'Marque',
        ] as $key => $label) {
            $rows[] = [$label, self::show($reading[$key] ?? null), self::show($fakeReading[$key] ?? null)];
        }
        $rows[] = ['Recherche', $identification->status->value, $fakeIdentification->status->value];
        $rows[] = ['Message', self::show($identification->message()), self::show($fakeIdentification->message())];
        $rows[] = ['Produit', self::show($identification->product?->code), self::show($fakeIdentification->product?->code)];

        $io->table(['', sprintf('Modèle réel (%s)', $exchange->provider->value), 'Lecteur de démo'], array_map(fn (array $row): array => array_map($this->masker->mask(...), $row), $rows));
        $io->note('Le lecteur de démo ne regarde pas l\'image : il lit le bloc texte caché des plaques FICTIF de public/yack/demo/plaques/. Toute autre photo est illisible pour lui.');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array{string, string}>
     */
    private function rows(array $data): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [$key, $this->masker->mask(self::show($value))];
        }

        return $rows;
    }

    private static function lookupLine(ApplianceIdentification $identification): string
    {
        $serial = self::show($identification->serialNumber);
        $product = $identification->product;

        return match ($identification->status) {
            IdentificationStatus::Found => sprintf('Appareil du parc : n° %s (même page qu\'une recherche manuelle : /sav/%s).', $serial, $serial),
            IdentificationStatus::Product => null === $product ? ApplianceIdentification::PRODUCT_MESSAGE : sprintf(
                '%s : %s (%s), fiche /produit/%s ; documents : %s.',
                ApplianceIdentification::PRODUCT_MESSAGE,
                $product->name,
                $product->code,
                $product->slug,
                [] === $product->documents ? 'aucun' : implode(', ', $product->documents),
            ),
            IdentificationStatus::Unknown => ApplianceIdentification::UNKNOWN_MESSAGE . ' : ni dans le parc installé, ni dans le catalogue.',
            IdentificationStatus::Unreadable => ApplianceIdentification::UNREADABLE_MESSAGE . '.',
            IdentificationStatus::Unavailable => ApplianceIdentification::UNAVAILABLE_MESSAGE . ' (la boutique répond 503).',
        };
    }

    /** @return array<string, mixed>|null */
    private static function readingData(?NameplateReading $reading): ?array
    {
        return null === $reading ? null : [
            'readable' => $reading->isReadable(),
            'serial_number' => $reading->serialNumber,
            'reference' => $reading->reference,
            'model' => $reading->model,
            'brand' => $reading->brand,
            'simulated' => $reading->simulated,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function lookupData(?ApplianceIdentification $identification): ?array
    {
        if (null === $identification) {
            return null;
        }
        $product = $identification->product;

        return [
            'status' => $identification->status->value,
            'message' => $identification->message(),
            'serial_number' => $identification->serialNumber,
            'reference' => $identification->reference,
            'model' => $identification->model,
            'simulated' => $identification->simulated,
            'product' => null === $product ? null : [
                'code' => $product->code,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand' => $product->brand,
                'documents' => $product->documents,
            ],
        ];
    }

    /**
     * Ce que l'application change à la réponse du modèle, en clair : c'est là qu'on voit pourquoi une lecture
     * correcte à l'œil peut finir « illisible » ou « non trouvée ».
     *
     * @param array<string, mixed> $fields
     *
     * @return list<string>
     */
    private static function notes(array $fields, ?NameplateReading $reading): array
    {
        if ([] === $fields || null === $reading) {
            return [];
        }

        $notes = [];
        $serial = \is_string($fields['serial'] ?? null) ? $fields['serial'] : null;
        $reference = \is_string($fields['reference'] ?? null) ? $fields['reference'] : null;
        $brand = \is_string($fields['brand'] ?? null) ? $fields['brand'] : null;
        if (true !== ($fields['readable'] ?? null)) {
            $notes[] = 'readable ne vaut pas true : la plaque est traitée comme illisible, le n° de série et la référence rendus ne sont pas gardés (seules la désignation et la marque le sont).';
        } else {
            if (null !== $serial && null === $reading->serialNumber) {
                $notes[] = sprintf('N° de série « %s » écarté : il ne ressemble pas à un numéro de série (6 à 32 lettres et chiffres, au moins un chiffre).', $serial);
            } elseif (null !== $serial && $reading->serialNumber !== $serial) {
                $notes[] = sprintf('N° de série normalisé : « %s » devient « %s ».', $serial, SerialNumberFormat::normalize($serial));
            }
            if (null !== $reference && null === $reading->reference) {
                $notes[] = sprintf('Référence « %s » écartée : plus de %d caractères, ou sans lettre ni chiffre.', $reference, NameplateReading::REFERENCE_MAX);
            }
            if (!$reading->isReadable()) {
                $notes[] = 'readable vaut true, mais ni n° de série ni référence n\'est gardé : l\'application traite la plaque comme illisible.';
            }
        }
        if (null !== $brand && null === $reading->brand) {
            $notes[] = sprintf('Marque « %s » écartée : plus de %d caractères, ou sans lettre ni chiffre.', $brand, NameplateReading::BRAND_MAX);
        }

        return $notes;
    }

    private function fail(SymfonyStyle $io, bool $json, string $message, int $code = Command::FAILURE): int
    {
        $message = $this->masker->mask($message);
        if ($json) {
            $io->writeln(self::json(['error' => $message]));
        } else {
            $io->error($message);
        }

        return $code;
    }

    private static function filled(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            null === $value => self::NO_VALUE,
            true === $value => 'true',
            false === $value => 'false',
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            default => (string) json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE),
        };
    }

    private static function json(mixed $value): string
    {
        return (string) json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function dimensions(?int $width, ?int $height): string
    {
        return null === $width || null === $height ? 'dimensions inconnues' : sprintf('%d x %d px', $width, $height);
    }

    private static function bytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? sprintf('%s Mo', number_format($bytes / 1024 / 1024, 1, ',', ' ')) : sprintf('%s Ko', number_format($bytes / 1024, 1, ',', ' '));
    }

    private static function milliseconds(int $ms): string
    {
        return number_format($ms, 0, ',', ' ') . ' ms';
    }

    private static function number(?int $value): string
    {
        return null === $value ? 'non renvoyé' : number_format($value, 0, ',', ' ');
    }
}
