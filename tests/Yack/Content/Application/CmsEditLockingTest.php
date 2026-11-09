<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Application\CmsEditLocking;
use App\Yack\Content\Domain\CmsEditLockAction;
use App\Yack\Content\Domain\CmsEditLocked;
use App\Yack\Content\Domain\CmsEditLockSituation;
use App\Yack\Content\Domain\CmsTemplateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(CmsEditLocking::class)]
final class CmsEditLockingTest extends TestCase
{
    private InMemoryCmsEditLocks $locks;

    private MockClock $clock;

    private CmsEditLocking $locking;

    private AdminUser $sophie;

    private AdminUser $paul;

    private CmsPage $page;

    #[\Override]
    protected function setUp(): void
    {
        $this->locks = new InMemoryCmsEditLocks();
        $this->clock = new MockClock('2026-11-08 14:05:00.250', 'UTC');
        $this->locking = new CmsEditLocking($this->locks, $this->clock);
        $this->sophie = self::admin(1, 'Sophie', 'Martin');
        $this->paul = self::admin(2, 'Paul', 'Durand');
        $this->page = self::page(10);
    }

    public function testTheFirstAdministratorTakesTheLockAtTheOpening(): void
    {
        $status = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);

        $this->assertSame(CmsEditLockSituation::Mine, $status->situation);
        $this->assertSame('Sophie Martin', $status->lock?->holder->name);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $status->lock->token);
        $this->assertSame('2026-11-08 14:05:00.000000', $status->lock->since->format('Y-m-d H:i:s.u'), 'seconds, as the database keeps them');
        $this->assertSame(1, $this->locks->writes);
    }

    public function testTheSecondAdministratorReadsWhoEditsAndSinceWhenWithoutTakingIt(): void
    {
        $first = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(60);

        $second = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);

        $this->assertSame(CmsEditLockSituation::Other, $second->situation);
        $this->assertSame('Sophie Martin', $second->lock?->holder->name);
        $this->assertSame($first->lock?->since, $second->lock->since);
        $this->assertSame(1, $this->locks->writes, 'nothing written for the second one');
        $this->assertSame(CmsEditLockSituation::Other, $this->locking->status($this->page, $this->paul)->situation);
        $this->assertSame(CmsEditLockSituation::Mine, $this->locking->status($this->page, $this->sophie)->situation);
    }

    public function testALockWithoutSignOfLifeForTwoMinutesIsFree(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);

        $this->clock->sleep(119);
        $this->assertSame(CmsEditLockSituation::Other, $this->locking->status($this->page, $this->paul)->situation);
        $this->clock->sleep(1);
        $this->assertSame(CmsEditLockSituation::Free, $this->locking->status($this->page, $this->paul)->situation);

        $taken = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);
        $this->assertSame(CmsEditLockSituation::Mine, $taken->situation);
        $this->assertSame('Paul Durand', $taken->lock?->holder->name);
        $this->assertSame('2026-11-08 14:07:00', $taken->lock->since->format('Y-m-d H:i:s'));
    }

    public function testTheSameAdministratorInAnotherTabSharesTheLock(): void
    {
        $first = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(40);

        $again = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, self::admin(1, 'Sophie', 'Martin'));

        $this->assertSame(CmsEditLockSituation::Mine, $again->situation);
        $this->assertSame($first->lock?->token, $again->lock?->token);
        $this->assertSame($first->lock?->since, $again->lock?->since);
        $this->assertSame('14:05:40', $again->lock?->heartbeatAt?->format('H:i:s'));
    }

    public function testHisOwnSilentLockKeepsItsTokenAndStartsAgain(): void
    {
        $first = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(300);

        $again = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);

        $this->assertSame($first->lock?->token, $again->lock?->token);
        $this->assertSame('14:10:00', $again->lock?->since->format('H:i:s'));
    }

    public function testASignOfLifeKeepsTheLockAndItsStart(): void
    {
        $first = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        for ($i = 0; $i < 10; ++$i) {
            $this->clock->sleep(30);
            $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->sophie, $first->lock?->token);
        }

        $status = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);
        $this->assertSame(CmsEditLockSituation::Other, $status->situation, 'five minutes later, still held');
        $this->assertSame('14:05:00', $status->lock?->since->format('H:i:s'));
        $this->assertSame('14:10:00', $status->lock->heartbeatAt?->format('H:i:s'));
    }

    public function testASignOfLifeWithoutTheTokenChangesNothing(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(30);

        $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->sophie, 'ffffffffffffffffffffffffffffffff');
        $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->sophie);
        $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->paul, 'x');
        $this->locking->apply(CmsEditLockAction::Heartbeat, self::page(11), $this->sophie, 'x');

        $this->assertSame(1, $this->locks->writes);
        $this->assertSame('14:05:00', $this->locks->find($this->page)?->heartbeatAt?->format('H:i:s'));
    }

    public function testTakingOverGivesTheLockAndTheFirstOneLearnsItAtHisNextSignOfLife(): void
    {
        $sophie = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(75);

        $paul = $this->locking->apply(CmsEditLockAction::Takeover, $this->page, $this->paul);
        $this->assertSame(CmsEditLockSituation::Mine, $paul->situation);
        $this->assertNotSame($sophie->lock?->token, $paul->lock?->token);
        $this->assertSame('14:06:15', $paul->lock?->since->format('H:i:s'));

        $this->clock->sleep(10);
        $told = $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->sophie, $sophie->lock?->token);
        $this->assertSame(CmsEditLockSituation::Other, $told->situation);
        $this->assertSame('Paul Durand', $told->lock?->holder->name);
        $this->assertSame('14:06:15', $told->lock->since->format('H:i:s'), 'when he took over');
        $this->assertSame($paul->lock->token, $this->locks->find($this->page)?->token, 'her sign of life did not take it back');
    }

    public function testAnEditorWhoseLockChangedHandsInBetweenIsNotGivenItBack(): void
    {
        $sophie = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(200);
        $paul = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);
        $this->locking->apply(CmsEditLockAction::Release, $this->page, $this->paul, $paul->lock?->token);

        $late = $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->sophie, $sophie->lock?->token);

        $this->assertSame(CmsEditLockSituation::Free, $late->situation, 'free, but no longer hers: the builder reloads');
        $this->assertSame('Paul Durand', $this->locks->find($this->page)?->holder->name);
    }

    public function testAnEditorBackFromALongPauseKeepsALockNobodyTook(): void
    {
        $sophie = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(600);

        $back = $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->sophie, $sophie->lock?->token);

        $this->assertSame(CmsEditLockSituation::Mine, $back->situation);
        $this->assertSame($sophie->lock?->token, $back->lock?->token);
        $this->assertSame('14:15:00', $back->lock?->since->format('H:i:s'));
    }

    public function testTakingOverAContentNeverLockedTakesIt(): void
    {
        $status = $this->locking->apply(CmsEditLockAction::Takeover, $this->page, $this->paul);

        $this->assertSame(CmsEditLockSituation::Mine, $status->situation);
    }

    public function testTakingOverHisOwnLockKeepsItsTokenAndStart(): void
    {
        $first = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->clock->sleep(20);
        $again = $this->locking->apply(CmsEditLockAction::Takeover, $this->page, $this->sophie);
        $this->assertSame($first->lock?->token, $again->lock?->token);
        $this->assertSame('14:05:00', $again->lock?->since->format('H:i:s'));

        $this->clock->sleep(500);
        $late = $this->locking->apply(CmsEditLockAction::Takeover, $this->page, $this->sophie);
        $this->assertSame($first->lock?->token, $late->lock?->token);
        $this->assertSame('14:13:40', $late->lock?->since->format('H:i:s'));
    }

    public function testGivingBackFreesTheLockOnlyWithItsToken(): void
    {
        $sophie = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);

        $this->locking->apply(CmsEditLockAction::Release, $this->page, $this->sophie, 'ffffffffffffffffffffffffffffffff');
        $this->locking->apply(CmsEditLockAction::Release, $this->page, $this->sophie);
        $this->locking->apply(CmsEditLockAction::Release, $this->page, $this->paul, $sophie->lock?->token);
        $this->assertSame(CmsEditLockSituation::Other, $this->locking->status($this->page, $this->paul)->situation);

        $released = $this->locking->apply(CmsEditLockAction::Release, $this->page, $this->sophie, $sophie->lock?->token);
        $this->assertSame(CmsEditLockSituation::Free, $released->situation);
        $this->assertNull($this->locks->find($this->page)?->heartbeatAt);
        $this->locking->apply(CmsEditLockAction::Release, $this->page, $this->sophie, $sophie->lock?->token);
        $this->assertSame(2, $this->locks->writes, 'given back once');
        $this->assertSame(CmsEditLockSituation::Mine, $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul)->situation);
    }

    public function testAPageGivenBackByItsTabComesBackWithTheSamePage(): void
    {
        // A page kept by the browser (back-forward cache): given back at `pagehide`, a sign of life at `pageshow`.
        $sophie = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->locking->apply(CmsEditLockAction::Release, $this->page, $this->sophie, $sophie->lock?->token);
        $this->clock->sleep(30);

        $back = $this->locking->apply(CmsEditLockAction::Heartbeat, $this->page, $this->sophie, $sophie->lock?->token);

        $this->assertSame(CmsEditLockSituation::Mine, $back->situation);
    }

    public function testOfTwoSimultaneousFirstTakesOnlyOneWins(): void
    {
        $this->locks->beforeNextWrite = fn () => $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);

        $sophie = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);

        $this->assertSame(CmsEditLockSituation::Other, $sophie->situation);
        $this->assertSame('Paul Durand', $sophie->lock?->holder->name);
        $this->assertSame(1, $this->locks->writes);
    }

    public function testOfTwoSimultaneousTakesOfASilentLockOnlyOneWins(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, self::admin(3, 'Léa', 'Petit'));
        $this->clock->sleep(150);
        $this->locks->beforeNextWrite = fn () => $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);

        $sophie = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);

        $this->assertSame(CmsEditLockSituation::Other, $sophie->situation);
        $this->assertSame('Paul Durand', $this->locks->find($this->page)?->holder->name);
    }

    public function testTheLastOfTwoSimultaneousTakeOversWins(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, self::admin(3, 'Léa', 'Petit'));
        $this->locks->beforeNextWrite = fn () => $this->locking->apply(CmsEditLockAction::Takeover, $this->page, $this->paul);

        $sophie = $this->locking->apply(CmsEditLockAction::Takeover, $this->page, $this->sophie);

        $this->assertSame(CmsEditLockSituation::Mine, $sophie->situation);
        $this->assertSame('Sophie Martin', $this->locks->find($this->page)?->holder->name);
    }

    public function testAnAnswerComesEvenWhenEveryWriteLosesTheRace(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);
        $this->clock->sleep(200);
        $losses = 0;
        $race = function () use (&$race, &$losses): void {
            ++$losses;
            $this->locks->put($this->page, $this->paul, 'abc', $this->clock->now()->modify('-1 hour'), $this->clock->now()->modify('-1 hour'));
            $this->locks->beforeNextWrite = $race;
        };
        $this->locks->beforeNextWrite = $race;

        $status = $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);

        $this->assertSame(5, $losses);
        $this->assertSame(CmsEditLockSituation::Free, $status->situation);
    }

    public function testASaveGoesThroughWhenTheLockIsFreeOrHis(): void
    {
        $this->assertSame('libre', $this->locking->save($this->page, $this->paul, static fn (): string => 'libre'));
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);
        $this->assertSame('le sien', $this->locking->save($this->page, $this->paul, static fn (): string => 'le sien'));
        $this->clock->sleep(120);
        $this->assertSame('perime', $this->locking->save($this->page, $this->sophie, static fn (): string => 'perime'));
        $this->assertSame(['commit', 'commit', 'commit'], $this->locks->transactions);
        $this->assertSame(1, $this->locks->writes, 'a save leaves the lock as it is');
    }

    public function testASaveIsRefusedWhileAnotherAdministratorHoldsTheLock(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);
        $written = new \ArrayObject();

        try {
            $this->locking->save($this->page, $this->sophie, static function () use ($written): void {
                $written->append('écrit');
            });
            $this->fail('CmsEditLocked expected');
        } catch (CmsEditLocked $locked) {
            $this->assertSame('Paul Durand modifie cette page en ce moment : votre enregistrement est refusé pour ne pas écraser son travail. Rouvrez le builder pour voir sa version, ou prenez la main.', $locked->getMessage());
            $this->assertSame('Paul Durand', $locked->lock->holder->name);
        }
        $this->assertCount(0, $written);
        $this->assertSame(['rollback'], $this->locks->transactions);
    }

    public function testATemplateIsNamedInTheRefusal(): void
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        (new \ReflectionProperty(CmsTemplate::class, 'id'))->setValue($template, 4);
        $this->locking->apply(CmsEditLockAction::Acquire, $template, $this->paul);

        $this->expectException(CmsEditLocked::class);
        $this->expectExceptionMessage('Paul Durand modifie ce template en ce moment');
        $this->locking->save($template, $this->sophie, static fn (): null => null);
    }

    public function testATakeOverJustBeforeTheSaveReadsTheLockRefusesTheSave(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->sophie);
        $this->locks->beforeSerializedRead = fn () => $this->locking->apply(CmsEditLockAction::Takeover, $this->page, $this->paul);

        $this->expectException(CmsEditLocked::class);
        $this->locking->save($this->page, $this->sophie, static fn (): string => 'jamais');
    }

    public function testFixturesCommandsAndNewContentsAreNeverBlocked(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->page, $this->paul);

        $this->assertSame('fixtures', $this->locking->save($this->page, null, static fn (): string => 'fixtures'));
        $this->assertSame('nouvelle', $this->locking->save(new CmsPage('nouvelle', 'Nouvelle'), $this->sophie, static fn (): string => 'nouvelle'));
        $this->assertSame([], $this->locks->transactions, 'not even read');
    }

    private static function admin(int $id, string $firstName, string $lastName): AdminUser
    {
        $admin = new AdminUser();
        (new \ReflectionProperty(AdminUser::class, 'id'))->setValue($admin, $id);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);
        $admin->setEmail(strtolower($firstName) . '@yack.test');

        return $admin;
    }

    private static function page(int $id): CmsPage
    {
        $page = new CmsPage('offre-' . $id, 'Offre');
        (new \ReflectionProperty(CmsPage::class, 'id'))->setValue($page, $id);

        return $page;
    }
}
