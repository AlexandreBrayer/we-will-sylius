<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Persistence\DoctrineCmsEditLocks;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Real SQL on an in-memory SQLite database (same table and unique constraints as the migration).
 *
 * @internal
 */
#[CoversClass(DoctrineCmsEditLocks::class)]
final class DoctrineCmsEditLocksTest extends TestCase
{
    private Connection $connection;

    private DoctrineCmsEditLocks $locks;

    private AdminUser $sophie;

    private AdminUser $paul;

    private CmsPage $page;

    #[\Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE yack_cms_edit_lock (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, page_id INTEGER DEFAULT NULL, template_id INTEGER DEFAULT NULL, admin_id INTEGER NOT NULL, token VARCHAR(32) NOT NULL, acquired_at DATETIME NOT NULL, heartbeat_at DATETIME DEFAULT NULL, version INTEGER NOT NULL)');
        $this->connection->executeStatement('CREATE UNIQUE INDEX uniq_yack_cms_edit_lock_page ON yack_cms_edit_lock (page_id)');
        $this->connection->executeStatement('CREATE UNIQUE INDEX uniq_yack_cms_edit_lock_template ON yack_cms_edit_lock (template_id)');
        $this->sophie = self::admin(1, 'Sophie', 'Martin');
        $this->paul = self::admin(2, 'Paul', 'Durand');
        $admins = [1 => $this->sophie, 2 => $this->paul];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);
        $entityManager->method('find')->willReturnCallback(static fn (string $class, int $id): ?AdminUser => AdminUser::class === $class ? $admins[$id] ?? null : null);
        $this->locks = new DoctrineCmsEditLocks($entityManager);
        $this->page = new CmsPage('offre', 'Offre');
        (new \ReflectionProperty(CmsPage::class, 'id'))->setValue($this->page, 10);
    }

    public function testAFirstLockIsInsertedAndReadBack(): void
    {
        $this->assertNull($this->locks->find($this->page));

        $this->assertTrue($this->locks->insert($this->page, $this->sophie, str_repeat('a', 32), new \DateTimeImmutable('2026-11-08 14:05:00')));

        $lock = $this->locks->find($this->page);
        $this->assertSame(1, $lock?->holderId);
        $this->assertSame('Sophie Martin', $lock->holder->name);
        $this->assertSame(str_repeat('a', 32), $lock->token);
        $this->assertSame('2026-11-08 14:05:00', $lock->since->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-08 14:05:00', $lock->heartbeatAt?->format('Y-m-d H:i:s'));
        $this->assertSame(1, $lock->version);
    }

    public function testTheUniqueTargetRefusesASecondInsert(): void
    {
        $this->locks->insert($this->page, $this->sophie, str_repeat('a', 32), new \DateTimeImmutable('2026-11-08 14:05:00'));

        $this->assertFalse($this->locks->insert($this->page, $this->paul, str_repeat('b', 32), new \DateTimeImmutable('2026-11-08 14:05:00')));
        $this->assertSame('Sophie Martin', $this->locks->find($this->page)?->holder->name);
        $this->assertSame(1, $this->rows());
    }

    public function testAPageAndATemplateOfTheSameIdHaveTheirOwnLock(): void
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        (new \ReflectionProperty(CmsTemplate::class, 'id'))->setValue($template, 10);
        $this->locks->insert($this->page, $this->sophie, str_repeat('a', 32), new \DateTimeImmutable('2026-11-08 14:05:00'));

        $this->assertNull($this->locks->find($template));
        $this->assertTrue($this->locks->insert($template, $this->paul, str_repeat('b', 32), new \DateTimeImmutable('2026-11-08 14:06:00')));
        $this->assertSame('Paul Durand', $this->locks->find($template)?->holder->name);
        $this->assertSame('Sophie Martin', $this->locks->find($this->page)?->holder->name);
    }

    public function testReplaceIsACompareAndSetOnTheVersion(): void
    {
        $this->locks->insert($this->page, $this->sophie, str_repeat('a', 32), new \DateTimeImmutable('2026-11-08 14:05:00'));
        $seen = $this->locks->find($this->page);
        $this->assertNotNull($seen);

        $this->assertTrue($this->locks->replace($this->page, $seen, $this->paul, str_repeat('b', 32), new \DateTimeImmutable('2026-11-08 14:08:00'), new \DateTimeImmutable('2026-11-08 14:08:30')));
        $this->assertFalse($this->locks->replace($this->page, $seen, $this->sophie, str_repeat('c', 32), new \DateTimeImmutable('2026-11-08 14:09:00'), new \DateTimeImmutable('2026-11-08 14:09:00')), 'the lock changed since it was read');

        $lock = $this->locks->find($this->page);
        $this->assertSame('Paul Durand', $lock?->holder->name);
        $this->assertSame(str_repeat('b', 32), $lock->token);
        $this->assertSame('14:08:00', $lock->since->format('H:i:s'));
        $this->assertSame('14:08:30', $lock->heartbeatAt?->format('H:i:s'));
        $this->assertSame(2, $lock->version);
    }

    public function testReplaceWithoutSignOfLifeGivesTheLockBack(): void
    {
        $this->locks->insert($this->page, $this->sophie, str_repeat('a', 32), new \DateTimeImmutable('2026-11-08 14:05:00'));
        $seen = $this->locks->find($this->page);
        $this->assertNotNull($seen);

        $this->locks->replace($this->page, $seen, $this->sophie, $seen->token, $seen->since, null);

        $this->assertNull($this->locks->find($this->page)?->heartbeatAt);
    }

    public function testALockOfAnUnsavedContentOrAVanishedAdministratorIsNone(): void
    {
        $this->assertNull($this->locks->find(new CmsPage('nouvelle', 'Nouvelle')));
        $this->connection->insert('yack_cms_edit_lock', ['page_id' => 10, 'admin_id' => 99, 'token' => 'x', 'acquired_at' => '2026-11-08 14:05:00', 'heartbeat_at' => null, 'version' => 1]);

        $this->assertNull($this->locks->find($this->page));
    }

    public function testSerializedRunsTheWorkInOneTransactionWithTheLockReadInside(): void
    {
        $this->locks->insert($this->page, $this->sophie, str_repeat('a', 32), new \DateTimeImmutable('2026-11-08 14:05:00'));

        $result = $this->locks->serialized($this->page, function (?CmsEditLockState $lock): string {
            $this->assertTrue($this->connection->isTransactionActive());
            $this->assertSame('Sophie Martin', $lock?->holder->name);
            $this->connection->insert('yack_cms_edit_lock', ['template_id' => 1, 'admin_id' => 2, 'token' => 'x', 'acquired_at' => '2026-11-08 14:05:00', 'version' => 1]);

            return 'enregistrée';
        });

        $this->assertSame('enregistrée', $result);
        $this->assertFalse($this->connection->isTransactionActive());
        $this->assertSame(2, $this->rows(), 'committed');
        $this->assertSame(1, $this->locks->find($this->page)?->version, 'the row is only locked, not changed');
    }

    public function testReadForWriteReadsTheLockInTheTransactionOfTheCallerWithoutChangingIt(): void
    {
        $this->locks->insert($this->page, $this->sophie, str_repeat('a', 32), new \DateTimeImmutable('2026-11-08 14:05:00'));
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        (new \ReflectionProperty(CmsTemplate::class, 'id'))->setValue($template, 10);

        $this->connection->beginTransaction();
        $lock = $this->locks->readForWrite($this->page);
        $none = $this->locks->readForWrite($template);
        $this->assertTrue($this->connection->isTransactionActive(), 'left open for the caller');
        $this->connection->commit();

        $this->assertSame('Sophie Martin', $lock?->holder->name);
        $this->assertNull($none, 'the template of the same id has no lock');
        $this->assertSame(1, $this->locks->find($this->page)?->version, 'the row is only locked, not changed');
    }

    public function testReadForWriteOutsideATransactionIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->locks->readForWrite($this->page);
    }

    public function testSerializedRollsBackWhenTheWorkThrows(): void
    {
        $thrown = null;

        try {
            $this->locks->serialized($this->page, function (?CmsEditLockState $lock): void {
                $this->assertNull($lock);
                $this->connection->insert('yack_cms_edit_lock', ['template_id' => 1, 'admin_id' => 2, 'token' => 'x', 'acquired_at' => '2026-11-08 14:05:00', 'version' => 1]);

                throw new \DomainException('refusée');
            });
        } catch (\DomainException $exception) {
            $thrown = $exception->getMessage();
        }

        $this->assertSame('refusée', $thrown);

        $this->assertFalse($this->connection->isTransactionActive());
        $this->assertSame(0, $this->rows());
    }

    public function testSerializedDoesNotRollBackTwiceWhenTheWorkAlreadyDid(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->locks->serialized($this->page, function (): never {
            $this->connection->rollBack();

            throw new \RuntimeException('flush manqué');
        });
    }

    private function rows(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM yack_cms_edit_lock');

        return is_numeric($count) ? (int) $count : -1;
    }

    private static function admin(int $id, string $firstName, string $lastName): AdminUser
    {
        $admin = new AdminUser();
        (new \ReflectionProperty(AdminUser::class, 'id'))->setValue($admin, $id);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);

        return $admin;
    }
}
