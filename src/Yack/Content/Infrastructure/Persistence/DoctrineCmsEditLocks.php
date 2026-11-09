<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsEditLocks;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Edit locks in `yack_cms_edit_lock` (App\Entity\Content\CmsEditLock), written through DBAL, outside the
 * unit of work of the request: a refused insert (unique target) does not close the entity manager, and a
 * lock read is never an entity left stale in the identity map.
 *  - insert(): the unique constraint on `page_id` or `template_id` decides between two first takes;
 *  - replace(): `UPDATE … WHERE version = :seen`, one row changed or none;
 *  - serialized(), readForWrite(): a write on the lock row (`SET version = version`) in the transaction: the
 *    row lock of InnoDB (the write lock of SQLite) makes a take over wait until the save commits.
 */
#[AsAlias(CmsEditLocks::class)]
final readonly class DoctrineCmsEditLocks implements CmsEditLocks
{
    private const string TABLE = 'yack_cms_edit_lock';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(CmsPage|CmsTemplate $target): ?CmsEditLockState
    {
        $id = $target->getId();
        if (null === $id) {
            return null;
        }
        $connection = $this->entityManager->getConnection();
        /** @var array{admin_id: int|string, token: string, acquired_at: string, heartbeat_at: string|null, version: int|string}|false $row */
        $row = $connection->fetchAssociative(
            sprintf('SELECT admin_id, token, acquired_at, heartbeat_at, version FROM %s WHERE %s = ?', self::TABLE, self::column($target)),
            [$id],
        );
        if (false === $row) {
            return null;
        }
        $format = '!' . $connection->getDatabasePlatform()->getDateTimeFormatString();
        $date = static fn (?string $value): ?\DateTimeImmutable => null === $value ? null : (\DateTimeImmutable::createFromFormat($format, $value) ?: null);
        $holder = $this->entityManager->find(AdminUser::class, (int) $row['admin_id']);
        $since = $date($row['acquired_at']);
        if (!$holder instanceof AdminUser || null === $since) {
            return null;
        }

        return new CmsEditLockState((int) $row['admin_id'], CmsRevisionAuthor::of($holder), $row['token'], $since, $date($row['heartbeat_at']), (int) $row['version']);
    }

    public function insert(CmsPage|CmsTemplate $target, AdminUser $holder, string $token, \DateTimeImmutable $now): bool
    {
        try {
            $this->entityManager->getConnection()->insert(self::TABLE, [
                self::column($target) => $target->getId(),
                'admin_id' => $holder->getId(),
                'token' => $token,
                'acquired_at' => $now,
                'heartbeat_at' => $now,
                'version' => 1,
            ], [
                'acquired_at' => Types::DATETIME_IMMUTABLE,
                'heartbeat_at' => Types::DATETIME_IMMUTABLE,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function replace(CmsPage|CmsTemplate $target, CmsEditLockState $seen, AdminUser $holder, string $token, \DateTimeImmutable $since, ?\DateTimeImmutable $heartbeatAt): bool
    {
        $changed = $this->entityManager->getConnection()->executeStatement(
            sprintf('UPDATE %s SET admin_id = ?, token = ?, acquired_at = ?, heartbeat_at = ?, version = version + 1 WHERE %s = ? AND version = ?', self::TABLE, self::column($target)),
            [$holder->getId(), $token, $since, $heartbeatAt, $target->getId(), $seen->version],
            [Types::INTEGER, Types::STRING, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::INTEGER, Types::INTEGER],
        );

        return 1 === $changed;
    }

    public function readForWrite(CmsPage|CmsTemplate $target): ?CmsEditLockState
    {
        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new \LogicException('Le verrou d\'édition se lit pour une écriture à l\'intérieur d\'une transaction.');
        }
        $connection->executeStatement(sprintf('UPDATE %s SET version = version WHERE %s = ?', self::TABLE, self::column($target)), [$target->getId()]);

        return $this->find($target);
    }

    public function serialized(CmsPage|CmsTemplate $target, callable $work): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $work($this->readForWrite($target));
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private static function column(CmsPage|CmsTemplate $target): string
    {
        return $target instanceof CmsPage ? 'page_id' : 'template_id';
    }
}
