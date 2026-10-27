<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

class DataConstraint
{
    /**
     * @var string
     */
    private $accessUseConstraint = '';

    /**
     * @var string
     */
    private $urlDataPolicy;

    /**
     * @return string
     */
    public function getAccessUseConstraint(): string
    {
        return $this->accessUseConstraint;
    }

    /**
     * @param string $accessUseConstraint
     */
    public function setAccessUseConstraint(string $accessUseConstraint): void
    {
        $this->accessUseConstraint = $accessUseConstraint;
    }

    /**
     * @return string
     */
    public function getUrlDataPolicy(): string
    {
        return $this->urlDataPolicy;
    }

    /**
     * @param string $urlDataPolicy
     */
    public function setUrlDataPolicy(string $urlDataPolicy): void
    {
        $this->urlDataPolicy = $urlDataPolicy;
    }
}
