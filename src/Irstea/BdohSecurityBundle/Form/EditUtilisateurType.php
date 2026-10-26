<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

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
