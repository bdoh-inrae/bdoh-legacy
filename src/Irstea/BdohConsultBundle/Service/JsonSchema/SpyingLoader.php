<?php

namespace Irstea\BdohConsultBundle\Service\JsonSchema;

use Opis\JsonSchema\ISchemaLoader;

final class SpyingLoader implements ISchemaLoader
{
    /** @var ISchemaLoader */
    private $decorated;

    /** @var string[] */
    private $schemas = [];

    public function __construct(ISchemaLoader $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function loadSchema(string $uri)
    {
        $this->schemas[$uri] = true;

        return $this->decorated->loadSchema($uri);
    }

    /**
     * @return string[]
     */
    public function getSchemas(): array
    {
        return array_keys($this->schemas);
    }
}
