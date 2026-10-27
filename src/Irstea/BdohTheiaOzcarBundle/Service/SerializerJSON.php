<?php

namespace Irstea\BdohTheiaOzcarBundle\Service;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohTheiaOzcarBundle\Mapper\MapperInterface;

final class SerializerJSON
{
    private $mapper;

    public function __construct(MapperInterface $mapper)
    {
        $this->mapper = $mapper;
    }

    public function serialize(Observatoire $observatoire): string
    {
        $formatPivot = $this->mapper->mapFormatPivotInterface($observatoire);

        return json_encode(self::normalize($formatPivot), JSON_THROW_ON_ERROR);
    }

    /**
     * Le format pivot comme l'écrivait JMS Serializer 1, sans métadonnées ni réglage, dans l'ancien BDOH :
     * toutes les propriétés que la classe d'un objet déclare, dans leur ordre, sans les valeurs nulles,
     * ni dans un objet ni dans un tableau ; un objet sans propriété en {} ; un tableau garde ses clés.
     */
    private static function normalize(mixed $value): mixed
    {
        if (\is_array($value)) {
            $array = [];
            foreach ($value as $key => $item) {
                if (null !== $item = self::normalize($item)) {
                    $array[$key] = $item;
                }
            }

            return $array;
        }

        if (!\is_object($value)) {
            return $value;
        }

        // JMS écrivait aussi les propriétés d'un \stdClass, les collections, les dates : le format pivot
        // n'en a pas ; un objet d'une autre sorte s'écrirait ici autrement, sans erreur
        if ($value instanceof \stdClass) {
            return self::normalize(get_object_vars($value)) ?: new \stdClass();
        }
        if (!str_starts_with($value::class, 'Irstea\\BdohTheiaOzcarBundle\\Model\\')) {
            throw new \LogicException(sprintf('Le format pivot ne sait pas écrire un objet %s.', $value::class));
        }

        $object = [];
        foreach ((new \ReflectionClass($value))->getProperties() as $property) {
            if (!$property->isStatic() && null !== $item = self::normalize($property->getValue($value))) {
                $object[$property->getName()] = $item;
            }
        }

        return $object === [] ? new \stdClass() : $object;
    }
}
