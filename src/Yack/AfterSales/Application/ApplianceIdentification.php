<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\Catalog\Domain\ProductReference;

/**
 * Result of IdentifyApplianceFromPhoto. `serialNumber` is the normalised serial read on the plate,
 * `reference` and `model` what is printed next to it, `product` the catalog product of a model reference
 * (status Product), `simulated` true with the demo reader.
 */
final readonly class ApplianceIdentification
{
    public const PRODUCT_MESSAGE = 'Modèle reconnu dans le catalogue';
    public const UNREADABLE_MESSAGE = 'Plaque illisible : saisissez le numéro de série';
    public const UNKNOWN_MESSAGE = 'Appareil non trouvé';
    public const UNAVAILABLE_MESSAGE = 'Lecture indisponible : saisissez le numéro de série';

    private function __construct(
        public IdentificationStatus $status,
        public ?string $serialNumber,
        public ?string $model,
        public ?string $reference,
        public ?ProductReference $product,
        public bool $simulated,
    ) {
    }

    public static function found(NameplateReading $reading): self
    {
        if (null === $reading->serialNumber) {
            throw new \InvalidArgumentException('Sans numéro de série, aucun appareil du parc.');
        }

        return self::fromReading(IdentificationStatus::Found, $reading);
    }

    public static function product(NameplateReading $reading, ProductReference $product): self
    {
        return new self(IdentificationStatus::Product, $reading->serialNumber, $reading->model, $reading->reference, $product, $reading->simulated);
    }

    public static function unknown(NameplateReading $reading): self
    {
        if (!$reading->isReadable()) {
            throw new \InvalidArgumentException('Une lecture illisible ne désigne aucun appareil.');
        }

        return self::fromReading(IdentificationStatus::Unknown, $reading);
    }

    public static function unreadable(NameplateReading $reading): self
    {
        return new self(IdentificationStatus::Unreadable, null, $reading->model, null, null, $reading->simulated);
    }

    public static function unavailable(): self
    {
        return new self(IdentificationStatus::Unavailable, null, null, null, null, false);
    }

    /** French message shown to the technician, null when the appliance was found. */
    public function message(): ?string
    {
        return match ($this->status) {
            IdentificationStatus::Found => null,
            IdentificationStatus::Product => self::PRODUCT_MESSAGE,
            IdentificationStatus::Unreadable => self::UNREADABLE_MESSAGE,
            IdentificationStatus::Unknown => self::UNKNOWN_MESSAGE,
            IdentificationStatus::Unavailable => self::UNAVAILABLE_MESSAGE,
        };
    }

    private static function fromReading(IdentificationStatus $status, NameplateReading $reading): self
    {
        return new self($status, $reading->serialNumber, $reading->model, $reading->reference, null, $reading->simulated);
    }
}
