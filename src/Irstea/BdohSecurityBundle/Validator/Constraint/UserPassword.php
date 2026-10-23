<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UserPassword extends Constraint
{
    public $message = 'Utilisateur.password.badCurrent';

    public function validatedBy()
    {
        return 'irstea_bdoh_security.validator.user_password';
    }
}
