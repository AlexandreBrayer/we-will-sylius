<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\Charte;
use App\Yack\Content\Infrastructure\Api\BuilderMeView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(BuilderMeView::class)]
final class BuilderMeViewTest extends TestCase
{
    public function testItReadsTheLevelAndFirstNameOfTheAdministrator(): void
    {
        $admin = new AdminUser();
        $admin->setFirstName('Admin');
        $admin->setBuilderLevel(BuilderLevel::LibreAvertissement);

        $me = BuilderMeView::of($admin);

        $this->assertSame('libre_avertissement', $me->level);
        $this->assertSame('Libre avec avertissement', $me->levelLabel);
        $this->assertSame('Admin', $me->firstName);
        $this->assertSame(Charte::toArray(), $me->charte);
    }

    public function testANewAdministratorIsAtTheCharteLevelWithoutFirstName(): void
    {
        $me = BuilderMeView::of(new AdminUser());

        $this->assertSame('charte', $me->level);
        $this->assertNull($me->firstName);
    }

    public function testTheItemGroupGivesTheContractOfTheBuilder(): void
    {
        $admin = new AdminUser();
        $admin->setFirstName('Admin');
        $admin->setBuilderLevel(BuilderLevel::Libre);
        $serializer = new Serializer([new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()))]);

        $data = $serializer->normalize(BuilderMeView::of($admin), null, ['groups' => ['builder_me:item']]);

        $this->assertIsArray($data);
        $this->assertSame(['level', 'levelLabel', 'firstName', 'charte'], array_keys($data));
        $this->assertSame('libre', $data['level']);
        $this->assertSame(['colors', 'titleSizes', 'spacings', 'radii'], array_keys($data['charte']));
        $this->assertSame(['key' => 'teal', 'label' => Charte::COLORS['teal'][0], 'value' => Charte::COLORS['teal'][1]], self::entry($data['charte']['colors'], 'teal'));
        $this->assertSame(['key', 'label', 'value'], array_keys($data['charte']['spacings'][0]));
    }

    /**
     * @param list<array{key: string, label: string, value: string}> $entries
     *
     * @return array{key: string, label: string, value: string}|null
     */
    private static function entry(array $entries, string $key): ?array
    {
        foreach ($entries as $entry) {
            if ($key === $entry['key']) {
                return $entry;
            }
        }

        return null;
    }
}
