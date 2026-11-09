<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Persistence;

use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CmsTemplates::class)]
final readonly class DoctrineCmsTemplates implements CmsTemplates
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(int $id): ?CmsTemplate
    {
        return $this->entityManager->find(CmsTemplate::class, $id);
    }

    public function findDefault(CmsTemplateType $type): ?CmsTemplate
    {
        return $this->entityManager->getRepository(CmsTemplate::class)
            ->findOneBy(['type' => $type, 'default' => true, 'status' => CmsPageStatus::Published]);
    }

    public function published(CmsTemplateType $type): array
    {
        return $this->entityManager->getRepository(CmsTemplate::class)
            ->findBy(['type' => $type, 'status' => CmsPageStatus::Published], ['name' => 'ASC']);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(CmsTemplate::class)
            ->findBy([], ['type' => 'ASC', 'name' => 'ASC']);
    }

    public function add(CmsTemplate $template): void
    {
        $this->entityManager->persist($template);
    }
}
