<?php

namespace Irstea\BdohAdminBundle\Form\Extension;

use Sonata\AdminBundle\Form\Type\Operator\StringOperatorType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Les filtres de texte des listes gardent les choix de Sonata 3 (contient, ne contient pas, est égal
 * à) : Sonata 4 en ajoute trois, qui changeraient les pages de l'ancien BDOH.
 */
final class StringOperatorTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [StringOperatorType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('choices', [
            'label_type_contains'     => StringOperatorType::TYPE_CONTAINS,
            'label_type_not_contains' => StringOperatorType::TYPE_NOT_CONTAINS,
            'label_type_equals'       => StringOperatorType::TYPE_EQUAL,
        ]);
    }
}
