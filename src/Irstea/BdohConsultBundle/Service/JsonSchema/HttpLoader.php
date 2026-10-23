<?php

namespace Irstea\BdohConsultBundle\Service\JsonSchema;

use Irstea\BdohConsultBundle\Exception\InvalidJsonSchemaURIException;
use Opis\JsonSchema\ISchemaLoader;
use Opis\JsonSchema\Schema;

final class HttpLoader implements ISchemaLoader
{
    /**
     * {@inheritdoc}
     */
    public function loadSchema(string $uri)
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidJsonSchemaURIException('Expected a http(s) URL, not ' . $scheme);
        }

        $content = file_get_contents($uri);
        if ($content === false) {
            throw new \RuntimeException('could not fetch ' . $uri);
        }

        return Schema::fromJsonString($content);
    }
}
