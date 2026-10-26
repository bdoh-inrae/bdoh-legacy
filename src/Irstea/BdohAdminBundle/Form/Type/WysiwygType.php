<?php

namespace Irstea\BdohAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class WysiwygType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'wysiwyg';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return TextareaType::class;
    }
}
