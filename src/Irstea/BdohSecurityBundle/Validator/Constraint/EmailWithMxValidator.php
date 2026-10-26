<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Reprend EmailValidator de Symfony 2.8, mode non strict et checkMX : une seule violation, que le
 * motif ou le domaine fasse défaut.
 */
class EmailWithMxValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        $value = (string) $value;
        $host = (string) substr($value, strrpos($value, '@') + 1);

        if (!preg_match('/^.+\@\S+\.\S+$/', $value) || '' === $host || !checkdnsrr($host, 'MX')) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->addViolation();
        }
    }
}
