<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class Datasets
{
    /** @var string */
    public $datasetId = '';

    /** @var Metadata */
    public $metadata;

    /** @var Observations[] */
    public $observations = [];

    public function __construct()
    {
        $this->metadata = new Metadata();
    }

    /**
     * @return string
     */
    public function getDatasetId(): string
    {
        return $this->datasetId;
    }

    /**
     * @param string $datasetId
     */
    public function setDatasetId(string $datasetId): void
    {
        $this->datasetId = $datasetId;
    }

    /**
     * @return Observations|Observations[]
     */
    public function getObservations()
    {
        return $this->observations;
    }

    /**
     * @param Observations|null $observation
     */
    public function addObservations(?Observations $observation): void
    {
        $this->observations[] = $observation;
    }
}
