<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsRevisionAuthor::class)]
final class CmsRevisionAuthorTest extends TestCase
{
    public function testTheHistoryShowsTheFirstAndLastName(): void
    {
        $author = CmsRevisionAuthor::of(self::admin('  Sophie ', 'Martin', 'sophie@yack.test'));

        $this->assertSame('Sophie', $author->firstName);
        $this->assertSame('Martin', $author->lastName);
        $this->assertSame('Sophie Martin', $author->name);
    }

    public function testOneNameIsEnough(): void
    {
        $author = CmsRevisionAuthor::of(self::admin(null, 'Martin', 'sophie@yack.test'));

        $this->assertNull($author->firstName);
        $this->assertSame('Martin', $author->name);
    }

    public function testWithoutNameTheHistoryShowsTheEmail(): void
    {
        $author = CmsRevisionAuthor::of(self::admin(' ', null, 'sophie@yack.test'));

        $this->assertNull($author->firstName);
        $this->assertNull($author->lastName);
        $this->assertSame('sophie@yack.test', $author->name);
    }

    public function testWithoutNameNorEmailTheHistoryShowsAnAdministrator(): void
    {
        $this->assertSame('Administrateur', CmsRevisionAuthor::of(self::admin(null, null, null))->name);
    }

    public function testANameLongerThanItsColumnIsCut(): void
    {
        $this->assertSame(255, mb_strlen((string) CmsRevisionAuthor::of(self::admin(str_repeat('é', 300), null, null))->firstName));
    }

    public function testTheShownNameFitsItsColumnWhenFirstAndLastNamesAreLong(): void
    {
        $author = CmsRevisionAuthor::of(self::admin(str_repeat('a', 200), str_repeat('b', 200), null));

        $this->assertSame(200, mb_strlen((string) $author->firstName));
        $this->assertSame(255, mb_strlen($author->name));
        $this->assertSame(str_repeat('a', 200) . ' ' . str_repeat('b', 54), $author->name);
    }

    public function testStoredColumnsGiveTheAuthorBack(): void
    {
        $this->assertNull(CmsRevisionAuthor::stored(null, null, null));
        $author = CmsRevisionAuthor::stored('Sophie', null, 'Sophie');
        $this->assertSame('Sophie', $author?->firstName);
        $this->assertNull($author->lastName);
        $this->assertSame('Sophie', $author->name);
    }

    private static function admin(?string $firstName, ?string $lastName, ?string $email): AdminUser
    {
        $admin = new AdminUser();
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);
        $admin->setEmail($email);

        return $admin;
    }
}
