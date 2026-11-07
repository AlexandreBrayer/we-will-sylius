<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Assistance\Application\FindManuals;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Lit `q`, `reference` et `serial` de la requête (un paramètre qui n'est pas un texte compte pour vide), refuse
 * une saisie hors des bornes de `FindManuals::problems` (422 sur le champ), puis cherche. Module coupé : 404.
 *
 * @implements ProviderInterface<ManualSearchView>
 */
final readonly class ManualSearchProvider implements ProviderInterface
{
    public const OPERATION = 'app_yack_api_shop_assistance_manuals_get';

    public const FIELDS = ['q', 'reference', 'serial'];

    public function __construct(
        private AssistanceSwitch $switch,
        private FindManuals $findManuals,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ManualSearchView
    {
        $this->switch->assertEnabled();

        $request = $context['request'] ?? null;
        $parameters = $request instanceof Request ? $request->query->all() : [];
        [$q, $reference, $serial] = array_map(
            static fn (string $field): string => \is_string($parameters[$field] ?? null) ? $parameters[$field] : '',
            self::FIELDS,
        );

        $problems = FindManuals::problems($q, $reference, $serial);
        if ([] !== $problems) {
            $violations = new ConstraintViolationList();
            foreach ($problems as $field => $message) {
                $violations->add(new ConstraintViolation($message, null, [], null, $field, null));
            }

            throw new ValidationException($violations);
        }

        return ManualSearchView::of(($this->findManuals)($q, $reference, $serial));
    }
}
