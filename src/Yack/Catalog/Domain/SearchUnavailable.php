<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** The search engine cannot answer (unreachable, timeout, error, missing index). */
final class SearchUnavailable extends \RuntimeException
{
}
