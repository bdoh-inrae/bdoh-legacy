<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Une adresse e-mail comme l'ancien BDOH la contrôlait (Email de Symfony 2.8, avec checkMX) : un
 * motif lâche, puis un enregistrement MX pour son domaine. Symfony 7 n'a plus checkMX.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class EmailWithMx extends Constraint
{
    public $message = 'Utilisateur.email.invalid';
}
