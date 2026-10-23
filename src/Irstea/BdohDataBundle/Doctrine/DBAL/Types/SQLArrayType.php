<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class SQLArrayType extends Type
{
    /**
     * @return Type
     */
    abstract protected function getInnerType();

    /**
     * @return Type
     */
    private function doGetInnerType()
    {
        static $type = null;
        if ($type === null) {
            return $type = $this->getInnerType();
        }

        return $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return '_' . $this->doGetInnerType()->getSQLDeclaration($column, $platform);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (!is_array($value)) {
            return null;
        }

        return $this->convertToDatabaseValueRecursive($value, $platform, $this->doGetInnerType());
    }

    /**
     * @param array            $values
     * @param AbstractPlatform $platform
     * @param Type             $type
     *
     * @return string
     */
    protected function convertToDatabaseValueRecursive(array $values, AbstractPlatform $platform, Type $type)
    {
        $result = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $result[] = $this->convertToDatabaseValueRecursive($value, $platform, $type);
            } else {
                $result[] = $type->convertToDatabaseValue($value, $platform);
            }
        }

        return '{' . implode(',', $result) . '}';
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->convertToPHPValueRecursive($value, $platform, $this->doGetInnerType());
    }

    /**
     * @param                  $values
     * @param AbstractPlatform $platform
     * @param Type             $type
     */
    protected function convertToPHPValueRecursive($values, AbstractPlatform $platform, Type $type)
    {
        throw new \RuntimeException(__METHOD__ . ' not yet implemented');
    }
}
