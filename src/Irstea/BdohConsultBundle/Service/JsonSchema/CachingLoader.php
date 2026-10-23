<?php

namespace Irstea\BdohConsultBundle\Service\JsonSchema;

use Doctrine\Common\Cache\ArrayCache;
use Opis\JsonSchema\ISchemaLoader;

final class CachingLoader implements ISchemaLoader
{
    /** @var ISchemaLoader */
    private $decorated;

    /** @var int */
    private $lifeTime;

    /** @var ArrayCache */
    private $cache;

    public function __construct(ISchemaLoader $decorated, int $lifeTime = 0)
    {
        $this->decorated = $decorated;
//        $this->apcuCache = $apcuCache ?: new ArrayCache();
        $this->lifeTime = $lifeTime;
        $this->cache =  new ArrayCache();
    }

    /**
     * {@inheritdoc}
     */
    public function loadSchema(string $uri)
    {
        if ($this->cache->contains($uri)) {
            return $this->cache->fetch($uri);
        }

        $schema = $this->decorated->loadSchema($uri);
        if ($schema !== null) {
            $this->cache->save($uri, $schema, $this->lifeTime);
        }

        return $schema;
    }
}
