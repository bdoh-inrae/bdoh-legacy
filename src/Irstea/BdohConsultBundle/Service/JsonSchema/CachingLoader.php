<?php

namespace Irstea\BdohConsultBundle\Service\JsonSchema;

use Opis\JsonSchema\ISchemaLoader;

/**
 * Les schémas déjà chargés, gardés le temps d'une requête, comme dans l'ancien BDOH (ArrayCache de
 * Doctrine Cache, bibliothèque retirée depuis ; le cache APCu prévu y était en commentaire).
 */
final class CachingLoader implements ISchemaLoader
{
    /** @var ISchemaLoader */
    private $decorated;

    /** @var array */
    private $cache = [];

    public function __construct(ISchemaLoader $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function loadSchema(string $uri)
    {
        if (\array_key_exists($uri, $this->cache)) {
            return $this->cache[$uri];
        }

        $schema = $this->decorated->loadSchema($uri);
        if ($schema !== null) {
            $this->cache[$uri] = $schema;
        }

        return $schema;
    }
}
