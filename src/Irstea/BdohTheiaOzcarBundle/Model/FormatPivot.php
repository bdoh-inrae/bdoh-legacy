<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class FormatPivot
{
    /** @var string */
    public $version = '1.1';

    /** @var Producer */
    public $producer;

    /** @var Datasets */
    public $datasets;

    public function __construct()
    {
        $this->producer = new Producer();
    }

    /**
     * @return Datasets[]|Datasets
     */
    public function getDatasets()
    {
        return $this->datasets;
    }

    /**
     * @param Datasets $datasets
     */
    public function setDatasets(Datasets $datasets): void
    {
        $this->datasets[] = $datasets;
    }

    /**
     * @return Producer
     */
    public function getProducer(): Producer
    {
        return $this->producer;
    }

    /**
     * @param Producer $producer
     */
    public function setProducer(Producer $producer): void
    {
        $this->producer = $producer;
    }
}
