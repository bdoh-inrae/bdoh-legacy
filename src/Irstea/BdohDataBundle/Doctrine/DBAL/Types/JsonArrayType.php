<?php

namespace Irstea\BdohDataBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Le type json_array de Doctrine DBAL 2.6, retiré de DBAL 3, que l'ancien BDOH employait : une
 * colonne JSON lue en tableau, où l'absence de valeur donne un tableau vide.
 */
class JsonArrayType extends Type
{
    const JSON_ARRAY = 'json_array';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $encoded = json_encode($value);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new ConversionException(sprintf('Could not convert PHP type "%s" to "json": %s', get_debug_type($value), json_last_error_msg()));
        }

        return $encoded;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        $value = is_resource($value) ? stream_get_contents($value) : $value;

        return json_decode($value, true);
    }
}
