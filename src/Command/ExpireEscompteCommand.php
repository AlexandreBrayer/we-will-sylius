<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\OrderProcessing\EscompteProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:escompte:expire',
    description: 'Retire l\'escompte des commandes non payées dont le délai est dépassé',
)]
final class ExpireEscompteCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans modifier la base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Mode dry-run : aucune modification ne sera effectuée.');
        }

        /** @var OrderInterface[] $orders */
        $orders = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->join('o.customer', 'c')
            ->where('o.paymentState = :awaitingPayment')
            ->andWhere('c.escompteDays IS NOT NULL')
            ->andWhere('c.escompteRate IS NOT NULL')
            ->setParameter('awaitingPayment', OrderPaymentStates::STATE_AWAITING_PAYMENT)
            ->getQuery()
            ->getResult();

        $expired = 0;

        foreach ($orders as $order) {
            $customer = $order->getCustomer();
            if (!$customer instanceof Customer) {
                continue;
            }

            $escompteDays = $customer->getEscompteDays();
            $createdAt = $order->getCreatedAt();

            if (null === $escompteDays || null === $createdAt) {
                continue;
            }

            $deadline = (new \DateTimeImmutable())->modify("-{$escompteDays} days");
            if ($createdAt >= $deadline) {
                // délai pas encore dépassé
                continue;
            }

            $hasEscompte = false;
            foreach ($order->getAdjustments(EscompteProcessor::ESCOMPTE_ADJUSTMENT) as $adjustment) {
                $hasEscompte = true;
                if (!$dryRun) {
                    $order->removeAdjustment($adjustment);
                }
            }

            if ($hasEscompte) {
                ++$expired;
                $io->writeln(sprintf(
                    'Commande %s (client %s) : escompte expiré (%d jours dépassés)%s',
                    $order->getNumber(),
                    $customer->getEmail(),
                    $escompteDays,
                    $dryRun ? ' [dry-run]' : '',
                ));
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d commande(s) traitée(s).', $expired));

        return Command::SUCCESS;
    }
}
