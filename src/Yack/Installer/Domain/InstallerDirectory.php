<?php

declare(strict_types=1);

namespace App\Yack\Installer\Domain;

/**
 * Port: the network of Yack partner installers (today an ERP/CRM export, tomorrow an API).
 */
interface InstallerDirectory
{
    /** @return list<Installer> */
    public function search(InstallerCriteria $criteria): array;

    /** @return list<string> */
    public function sectors(): array;

    /** @return list<string> */
    public function ranges(): array;
}
