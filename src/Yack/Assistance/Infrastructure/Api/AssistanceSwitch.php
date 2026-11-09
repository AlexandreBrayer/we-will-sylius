<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Interrupteur du module (YACK_ASSISTANCE : 1 actif, 0 coupé) : masquer sans supprimer. Chaque provider ou
 * processor du contexte l'appelle en premier ; coupé, l'API répond 404 comme si la route n'existait pas.
 */
final readonly class AssistanceSwitch
{
    public function __construct(
        #[Autowire(env: 'bool:YACK_ASSISTANCE')]
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** @throws NotFoundHttpException module coupé */
    public function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('Page introuvable.');
        }
    }
}
