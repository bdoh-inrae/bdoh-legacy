<?php

namespace Irstea\BdohSecurityBundle\Form;

/**
 * {@inheritdoc}
 */
class NewUtilisateurType extends UtilisateurType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'irstea_bdohsecuritybundle_newutilisateurtype';
    }
}
