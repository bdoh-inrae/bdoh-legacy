<?php declare(strict_types=1);
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Class CheckedPasswordTransformer.
 */
final class CheckedPasswordTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        return ['main' => $value, 'repeat' => null];
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (is_array($value) && \array_key_exists('main', $value)) {
            return $value['main'];
        }

        return null;
    }
}
