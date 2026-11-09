<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\TestCase;

/**
 * Sylius fait tourner API Platform avec les écouteurs Symfony (`use_symfony_listeners: true`) : le contrôleur
 * générique d'API Platform reçoit la donnée de la requête, posée par l'étape de lecture (`read`) ou par la
 * désérialisation. Une opération sans contrôleur propre qui coupe les deux (`read: false`, `deserialize: false`)
 * répond 500 (« Could not resolve argument $data »). Pour un envoi traité par le processor seul (photo,
 * fichier), on garde `read: true` sans provider : la donnée vaut null.
 *
 * @internal
 */
final class ApiOperationsTest extends TestCase
{
    public function testEveryOperationGivesItsDataToTheController(): void
    {
        $broken = [];
        foreach (Rules::files('src', '#\.php$#') as $file) {
            $source = (string) file_get_contents(Rules::root().'/'.$file);
            $class = self::declaredClass($source);
            if (null === $class || !str_contains($source, '#[ApiResource(')) {
                continue;
            }

            foreach ((new \ReflectionClass($class))->getAttributes(ApiResource::class) as $attribute) {
                foreach ($attribute->newInstance()->getOperations() ?? [] as $operation) {
                    if (self::losesItsData($operation)) {
                        $broken[] = $file.' : '.($operation->getName() ?? $operation->getUriTemplate());
                    }
                }
            }
        }

        $this->assertSame([], $broken, 'Mettre read: true (sans provider, la donnée vaut null) ou laisser la désérialisation.');
    }

    public function testTheRuleBites(): void
    {
        $this->assertTrue(self::losesItsData(new Post(read: false, deserialize: false)));
        $this->assertFalse(self::losesItsData(new Post(read: true, deserialize: false)));
        $this->assertFalse(self::losesItsData(new Post(read: false)));
        $this->assertFalse(self::losesItsData(new Post(controller: 'app.controller', read: false, deserialize: false)));
        $this->assertSame('App\\X\\Vue', self::declaredClass("<?php\nnamespace App\\X;\n\n#[ApiResource(\n)]\nfinal readonly class Vue\n{\n}"));
        $this->assertNull(self::declaredClass("<?php\nnamespace App\\X;\n\ninterface Port\n{\n}"));
    }

    private static function losesItsData(object $operation): bool
    {
        return $operation instanceof HttpOperation
            && null === $operation->getController()
            && false === $operation->canRead()
            && false === $operation->canDeserialize();
    }

    /** @return class-string|null */
    private static function declaredClass(string $source): ?string
    {
        if (1 !== preg_match('/^namespace ([\w\\\\]+);/m', $source, $namespace)
            || 1 !== preg_match('/^(?:(?:final|abstract|readonly) )*class (\w+)/m', $source, $class)) {
            return null;
        }

        /** @var class-string $name */
        $name = $namespace[1].'\\'.$class[1];

        return $name;
    }
}
