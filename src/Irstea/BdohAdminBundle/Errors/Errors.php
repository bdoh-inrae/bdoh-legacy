<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Errors;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class providing errors management.
 * For an example, see "ImportErrors.php".
 */
abstract class Errors
{
    const ERRORS_CATALOG = 'errors';

    /**
     * Number of errors.
     */
    public $count = 0;

    /**
     * Storage of errors.
     */
    protected $errors = [];

    /**
     * Returns true if no error have been stored.
     */
    public function isClean()
    {
        return $this->errors === [];
    }

    /**
     * Returns true if some errors have been stored.
     */
    public function isDirty()
    {
        return $this->errors !== [];
    }

    /**
     * Resolves the error translations and returns a flat array containing them.
     *
     * @param TranslatorInterface $translator
     *
     * @return array A flat array containing strings
     */
    public function translations(TranslatorInterface $translator)
    {
        $errorsTrans = [];

        foreach ($this->errors as $type => $errorsForType) {
            foreach ($errorsForType as $params) {
                $errorsTrans[] = $translator->trans($type, $params, static::ERRORS_CATALOG);
            }
        }

        return $errorsTrans;
    }

    /**
     * Facilitator allowing to add an error to the $errors attribute.
     * Takes as parameters :
     *    => the type of error to add ;
     *    => a variable and even number of parameters, being alternately :
     *       -> a translation placeholder ;
     *       -> a value for this placeholder .
     *
     * @param mixed $type
     */
    public function addError($type)
    {
        $params = [];

        for ($i = 1; $i < func_num_args(); $i += 2) {
            $params['%' . func_get_arg($i) . '%'] = func_get_arg($i + 1);
        }
        $this->errors[$type][] = $params;

        ++$this->count;
    }
}
