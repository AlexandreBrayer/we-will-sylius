<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/**
 * Port: the notices available to the assistant (FICTIF Markdown corpus in the POC; the real
 * notices of Yack later: same port, another adapter).
 */
interface DocumentationCorpus
{
    /** @return list<Notice> */
    public function notices(): array;
}
