<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;

/**
 * Liens que Sylius écrit vers la boutique (e-mails, back-office) : configuration de la stack.
 *
 * FRONT_URL vient du shell (ou de l'hébergement), sinon d'une ligne de .env.local, sinon du port publié par nginx
 * (http://localhost:<YACK_HTTP_PORT>). Le calcul vit dans config/packages/yack.yaml ; compose.yml ne passe au conteneur
 * que ce que le shell pose, pour ne pas masquer .env.local.
 *
 * @internal
 */
final class FrontLinksConfigurationTest extends TestCase
{
    private const VARIABLES = ['FRONT_URL', 'YACK_HTTP_PORT', 'SYMFONY_DOTENV_VARS'];

    /** Classe du conteneur compilé à partir des paramètres de yack.yaml, compilée une fois. */
    private static ?string $containerClass = null;

    /**
     * Valeurs de $_ENV, $_SERVER et getenv() avant le test, par variable.
     *
     * @var array<string, array{mixed, mixed, string|false}>
     */
    private array $saved = [];

    #[\Override]
    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->saved as $name => [$env, $server, $getenv]) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
            if (null !== $env) {
                $_ENV[$name] = $env;
            }
            if (null !== $server) {
                $_SERVER[$name] = $server;
            }
            if (false !== $getenv) {
                putenv($name . '=' . $getenv);
            }
        }
    }

    /**
     * @param array<string, string> $environment variables réelles du conteneur php (shell, puis compose.yml)
     */
    #[DataProvider('frontUrls')]
    public function testFrontUrlComesFromTheShellThenEnvLocalThenThePublishedPort(array $environment, ?string $envLocal, string $expected): void
    {
        foreach ($environment as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        if (null !== $envLocal) {
            // Même chargement que bin/console : une variable réelle passe devant la ligne du fichier.
            $dotenv = new Dotenv();
            $dotenv->populate($dotenv->parse($envLocal, '.env.local'));
        }

        $this->assertSame($expected, $this->container()->getParameter('app.yack.front_url'));
    }

    /** @return \Iterator<string, array{array<string, string>, ?string, string}> */
    public static function frontUrls(): \Iterator
    {
        yield 'rien posé (hors Docker, port du Makefile)' => [[], null, 'http://localhost:8088'];
        yield 'port publié 8090 (make YACK_HTTP_PORT=8090)' => [['YACK_HTTP_PORT' => '8090'], null, 'http://localhost:8090'];
        yield 'FRONT_URL du shell' => [['YACK_HTTP_PORT' => '8090', 'FRONT_URL' => 'https://boutique.example'], null, 'https://boutique.example'];
        yield 'FRONT_URL vide' => [['YACK_HTTP_PORT' => '8090', 'FRONT_URL' => ''], null, 'http://localhost:8090'];
        yield 'ligne de .env.local' => [['YACK_HTTP_PORT' => '8090'], "FRONT_URL=https://local.example\n", 'https://local.example'];
        yield 'FRONT_URL du shell et ligne de .env.local' => [['YACK_HTTP_PORT' => '8090', 'FRONT_URL' => 'https://boutique.example'], "FRONT_URL=https://local.example\n", 'https://boutique.example'];
    }

    public function testComposePassesTheShellFrontUrlAndThePortNginxPublishes(): void
    {
        $compose = Yaml::parseFile(Rules::root() . '/compose.yml');
        $override = Yaml::parseFile(Rules::root() . '/compose.override.dist.yml');
        $this->assertIsArray($compose);
        $this->assertIsArray($override);
        $environment = $compose['services']['php']['environment'];

        // Sans valeur : Compose ne la passe que si le shell la pose (une valeur par défaut masquerait .env.local).
        $this->assertArrayHasKey('FRONT_URL', $environment);
        $this->assertNull($environment['FRONT_URL']);
        $php = self::defaultPort('/^\$\{HTTP_PORT:-(\d+)\}$/', $environment['YACK_HTTP_PORT']);
        $nginx = self::defaultPort('/^\$\{HTTP_PORT:-(\d+)\}:80$/', $override['services']['nginx']['ports'][0]);
        $this->assertNotNull($php);
        $this->assertSame($nginx, $php, 'Sans make, FRONT_URL doit suivre le port que nginx publie.');
    }

    public function testDotEnvDoesNotPinTheFrontUrl(): void
    {
        // Compose lit aussi .env : une FRONT_URL (ou un YACK_HTTP_PORT) posée là serait passée au conteneur et figerait le port.
        $dotEnv = (string) file_get_contents(Rules::root() . '/.env');

        $this->assertSame(0, preg_match('/^\s*(export\s+)?(FRONT_URL|YACK_HTTP_PORT)\s*=/m', $dotEnv));
    }

    /** Port par défaut d'une expression ${HTTP_PORT:-<port>} de Compose. */
    private static function defaultPort(string $pattern, mixed $value): ?string
    {
        return \is_string($value) && preg_match($pattern, $value, $match) ? $match[1] : null;
    }

    private function container(): Container
    {
        if (null === self::$containerClass) {
            $yack = Yaml::parseFile(Rules::root() . '/config/packages/yack.yaml', Yaml::PARSE_CUSTOM_TAGS);
            $this->assertIsArray($yack);
            $builder = new ContainerBuilder();
            // Les défauts env(...) (dont l'absence de env(FRONT_URL), qui masquerait le repli) et le calcul de l'adresse.
            foreach ($yack['parameters'] as $name => $value) {
                if (str_starts_with((string) $name, 'env(') || str_ends_with((string) $name, 'front_url')) {
                    $builder->setParameter((string) $name, $value);
                }
            }
            $builder->compile();
            self::$containerClass = 'FrontLinksConfigurationContainer' . bin2hex(random_bytes(6));
            $code = (new PhpDumper($builder))->dump(['class' => self::$containerClass]);
            $this->assertIsString($code);
            eval('?>' . $code);
        }
        $container = new (self::$containerClass)();
        $this->assertInstanceOf(Container::class, $container);

        return $container;
    }
}
