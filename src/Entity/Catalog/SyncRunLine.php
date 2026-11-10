<?php

declare(strict_types=1);

namespace App\Entity\Catalog;

use App\Yack\Catalog\Domain\FieldChange;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use Doctrine\ORM\Mapping as ORM;

/**
 * One record of a synchronisation (SyncRun): the product, its outcome and its field-level changes
 * (`changes`: field, label, before, after), or, for a rejected record, the reason and the record as
 * received (`payload`, in the format of the API feed), kept to be corrected and replayed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'yack_sync_run_line')]
#[ORM\Index(name: 'idx_yack_sync_run_line_outcome', columns: ['run_id', 'outcome'])]
class SyncRunLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SyncRun::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'run_id', nullable: false, onDelete: 'CASCADE')]
    private SyncRun $run;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(type: 'string', length: 255)]
    private string $code;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name;

    #[ORM\Column(type: 'string', length: 16, enumType: SyncOutcome::class)]
    private SyncOutcome $outcome;

    /** @var list<array{field: string, label: string, before: string|null, after: string|null}> */
    #[ORM\Column(type: 'json')]
    private array $changes = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    /** Line of the imported file. */
    #[ORM\Column(name: 'file_line', type: 'integer', nullable: true)]
    private ?int $fileLine = null;

    private function __construct(SyncRun $run, int $position, string $code, ?string $name, SyncOutcome $outcome)
    {
        $this->run = $run;
        $this->position = $position;
        $this->code = mb_substr($code, 0, 255);
        $this->name = null === $name ? null : mb_substr($name, 0, 255);
        $this->outcome = $outcome;
    }

    public static function change(SyncRun $run, int $position, ProductChange $change): self
    {
        $line = new self($run, $position, $change->code, $change->name, $change->outcome);
        $line->changes = array_map(static fn (FieldChange $field): array => $field->toArray(), $change->fields);

        return $line;
    }

    public static function rejection(SyncRun $run, int $position, RejectedRecord $failure): self
    {
        $line = new self($run, $position, $failure->reference, $failure->name(), SyncOutcome::Failed);
        $line->reason = $failure->reason;
        $line->payload = [] === $failure->payload ? null : $failure->payload;
        $line->fileLine = $failure->line;

        return $line;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRun(): SyncRun
    {
        return $this->run;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getOutcome(): SyncOutcome
    {
        return $this->outcome;
    }

    /** @return list<FieldChange> */
    public function getChanges(): array
    {
        return array_map(FieldChange::fromArray(...), $this->changes);
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    /** @return array<string, mixed>|null */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /** Record as received, indented, for the detail page. */
    public function payloadJson(): ?string
    {
        return null === $this->payload ? null : json_encode($this->payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    public function getFileLine(): ?int
    {
        return $this->fileLine;
    }
}
