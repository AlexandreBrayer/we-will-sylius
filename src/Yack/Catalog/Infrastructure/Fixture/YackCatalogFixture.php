<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Fixture;

use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncReport;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Seeds the demo catalog through the very same pipeline as the PIM/ERP sync:
 * the fixture is just "the first Yack export".
 */
final class YackCatalogFixture extends AbstractFixture
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function getName(): string
    {
        return 'yack_catalog';
    }

    public function load(array $options): void
    {
        $feed = $options['feed'] ?? null;
        if (!is_string($feed)) {
            throw new \InvalidArgumentException('Yack catalog seed: the "feed" option must be a path.');
        }

        $report = $this->messageBus
            ->dispatch(new SynchronizeCatalog($feed))
            ->last(HandledStamp::class)
            ?->getResult();

        if (!$report instanceof SyncReport || $report->hasFailures()) {
            $reasons = $report instanceof SyncReport
                ? implode(' | ', array_map(static fn (RejectedRecord $failure): string => $failure->reference . ': ' . $failure->reason, $report->failures()))
                : 'no report';

            throw new \RuntimeException(sprintf('Yack catalog seed "%s" failed: %s', $feed, $reasons));
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->scalarNode('feed')->isRequired()->cannotBeEmpty()->end()
            ->end()
        ;
    }
}
