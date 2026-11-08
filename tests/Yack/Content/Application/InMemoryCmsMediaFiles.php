<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaFiles;

/** Hand-written fake of the file port: stored bytes by path, deleted paths in order. */
final class InMemoryCmsMediaFiles implements CmsMediaFiles
{
    /** @var array<string, string> */
    public array $files = [];

    /** @var list<string> */
    public array $deleted = [];

    public function store(CmsMediaFile $file): string
    {
        $path = CmsMediaFile::DIRECTORY . '/' . str_pad(dechex(count($this->files) + 1), 32, '0', \STR_PAD_LEFT) . '.' . $file->extension();
        $this->files[$path] = $file->bytes;

        return $path;
    }

    public function delete(string $path): void
    {
        $this->deleted[] = $path;
        unset($this->files[$path]);
    }
}
