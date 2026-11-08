<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsMedia;
use App\Yack\Content\Domain\CmsMediaFiles;
use App\Yack\Content\Domain\CmsMediaInUse;
use App\Yack\Content\Domain\CmsMediaLibrary;
use App\Yack\Content\Domain\CmsMediaUsage;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\PageBlocks;

/**
 * Deletes an image of the media library, refused while a page or a template (draft or published) shows
 * it. The file is deleted only for an uploaded image: a historical visual leaves the library but keeps
 * its file, which the repository holds and the blocks still accept.
 */
final readonly class DeleteCmsMedia
{
    public function __construct(
        private CmsMediaLibrary $library,
        private CmsMediaFiles $files,
        private CmsPages $pages,
        private CmsTemplates $templates,
    ) {
    }

    /**
     * @throws CmsMediaInUse
     */
    public function __invoke(CmsMedia $media): void
    {
        $usages = $this->usages($media);
        if ([] !== $usages) {
            throw new CmsMediaInUse($usages);
        }

        $path = $media->getPath();
        $uploaded = $media->isUploaded();
        $this->library->remove($media);
        if ($uploaded) {
            $this->files->delete($path);
        }
    }

    /**
     * Pages (last modified first), then templates (by type and name), whose blocks show the image.
     *
     * @return list<CmsMediaUsage>
     */
    public function usages(CmsMedia $media): array
    {
        $usages = [];
        foreach ($this->pages->all() as $page) {
            if (\in_array($media->getPath(), PageBlocks::images($page->getBlocks()), true)) {
                $usages[] = new CmsMediaUsage(CmsMediaUsage::PAGE, $page->getSlug(), $page->getTitle());
            }
        }
        foreach ($this->templates->all() as $template) {
            if (\in_array($media->getPath(), PageBlocks::images($template->getBlocks()), true)) {
                $usages[] = new CmsMediaUsage(CmsMediaUsage::TEMPLATE, (string) $template->getId(), $template->getName());
            }
        }

        return $usages;
    }
}
