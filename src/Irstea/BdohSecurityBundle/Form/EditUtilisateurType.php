<?php

namespace Irstea\BdohSecurityBundle\Form;

class EditUtilisateurType extends UtilisateurType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'irstea_bdohsecuritybundle_editutilisateurtype';
    }
}
