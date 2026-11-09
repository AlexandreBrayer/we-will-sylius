<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Application;

use App\Yack\Documentation\Domain\DocumentationCorpus;

/** What the documentation holds for a product: titles of its notices and a sample question. */
final readonly class DescribeProductDocumentation
{
    public function __construct(private DocumentationCorpus $corpus)
    {
    }

    public function __invoke(string $productCode): ProductDocumentation
    {
        $code = strtoupper(trim($productCode));
        $titles = [];
        $example = null;
        foreach ($this->corpus->notices() as $notice) {
            if (!$notice->covers($code)) {
                continue;
            }
            $titles[] = $notice->title;
            $example ??= $notice->exampleQuestion;
        }

        return new ProductDocumentation($code, $titles, $example);
    }
}
