<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Form;

use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormInterface;

/**
 * « Template CMS » select of the back-office product / taxon forms: published templates of the
 * type (plus the current one, even unpublished, so that saving the form keeps it); empty = inherited.
 */
final readonly class CmsTemplateChoices
{
    public function __construct(private CmsTemplates $templates)
    {
    }

    /**
     * @param FormInterface<mixed> $form
     */
    public function add(FormInterface $form, string $field, CmsTemplateType $type, ?CmsTemplate $current, string $label): void
    {
        $choices = $this->templates->published($type);
        if (null !== $current && !in_array($current, $choices, true)) {
            $choices[] = $current;
        }

        $form->add($field, EntityType::class, [
            'class' => CmsTemplate::class,
            'choices' => $choices,
            'choice_label' => static fn (CmsTemplate $template): string => $template->getName()
                . ($template->isDefault() ? ' (défaut)' : '')
                . ($template->isPublished() ? '' : ' — brouillon, non appliqué'),
            'choice_value' => 'id',
            'placeholder' => 'Hérité',
            'required' => false,
            'label' => $label,
        ]);
    }
}
