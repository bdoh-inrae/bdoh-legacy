<?php

namespace Irstea\BdohTheiaOzcarBundle\Service;

use Irstea\BdohConsultBundle\Service\JsonSchema\SpyingLoader;
use Opis\JsonSchema\ISchema;
use Opis\JsonSchema\ISchemaLoader;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;

final class SchemaJSON
{
    /** @var string */
    private $uriTheiaOzcarValidateur;

    /**
     * @var ISchemaLoader
     */
    private $loader;

    public function __construct(string $uriTheiaOzcarValidateur, ISchemaLoader $loader)
    {
        $this->uriTheiaOzcarValidateur = $uriTheiaOzcarValidateur;
        $this->loader = $loader;
    }

    /**
     * @param $jsonDecode
     */
    public function validateSchemaTheia($jsonDecode): ValidationResult
    {
        $schema = $this->loadSchemaTheia();
        $validator = new Validator();
        /** @var ValidationResult $result */
        $result = $validator->schemaValidation($jsonDecode, $schema, 10, $this->loader);

        return $result;
    }

    public function loadSchemaTheia(): ISchema
    {
        $spyingLoader = new SpyingLoader($this->loader);
        $schema = $spyingLoader->loadSchema($this->uriTheiaOzcarValidateur);

        return $schema;
    }
}
