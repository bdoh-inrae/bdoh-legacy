<?php

namespace Irstea\BdohAdminBundle\Form\Type;

use Irstea\BdohAdminBundle\Form\DataTransformer\PreciseNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Un champ de nombre qui garde la valeur saisie (PreciseNumberTransformer) : les seuils de validité
 * d'une chronique.
 */
final class PreciseNumberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addViewTransformer(new PreciseNumberTransformer());
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }
}
