<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class OnlineResource
{
    /**
     * @var string
     */
    private $urlDownload;

    /**
     * @var string
     */
    private $urlInfo;

    /**
     * @var string
     */
    private $doi;

    /**
     * @return string|null
     */
    public function getUrlDownload(): ?string
    {
        return $this->urlDownload;
    }

    /**
     * @param string $urlDownload
     */
    public function setUrlDownload(string $urlDownload): void
    {
        $this->urlDownload = $urlDownload;
    }

    /**
     * @return string
     */
    public function getUrlInfo(): string
    {
        return $this->urlInfo;
    }

    /**
     * @param string $urlInfo
     */
    public function setUrlInfo(string $urlInfo): void
    {
        $this->urlInfo = $urlInfo;
    }

    /**
     * @return string|null
     */
    public function getDoi(): ?string
    {
        return $this->doi;
    }

    /**
     * @param string|null $doi
     */
    public function setDoi(?string $doi)
    {
        $this->doi = $doi;
    }
}
