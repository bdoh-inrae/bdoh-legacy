<?php

namespace Irstea\BdohAdminBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class TheiaThesaurusTransformer implements DataTransformerInterface
{
    /**
     * Transforms a string ($uriString) to an array ($uriArray).
     *
     * @param array|null $value
     *
     * @return string|null
     */
    public function transform($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return implode(',', $value);
    }

    /**
     * Transforms an array ($uriArray) to a string ($uriString).
     *
     * @param string|null $value
     *
     * @return array|null
     */
    public function reverseTransform($value): array
    {
        if (empty($value)) {
            return [];
        }

        return explode(',', $value);
    }
}
