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
     * {@inheritdoc}
     */
    public function transform(mixed $value): mixed
    {
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
            return $value['current'];
        }

        return null;
    }
}
