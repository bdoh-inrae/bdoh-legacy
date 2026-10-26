<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class FileTransformer implements DataTransformerInterface
{
    /**
     * Le chemin du fichier enregistré : le champ caché « current » ne fait que le garder, ou le retirer
     * s'il revient vide ; un autre chemin, fabriqué, ne s'enregistre pas.
     */
    private $original;

    /**
     * {@inheritdoc}
     */
    public function transform(mixed $value): mixed
    {
        $this->original = empty($value) ? null : $value;
        if (empty($value)) {
            return ['new' => null, 'current' => null];
        }

        return ['new' => null, 'current' => $value];
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform(mixed $value): mixed
    {
        if (isset($value['new'])) {
            return $value['new'];
        }
        if (isset($value['current'])) {
            return $value['current'] === '' ? '' : $this->original;
        }

        return null;
    }
}
