<?php

namespace Irstea\BdohTheiaOzcarBundle\Model;

final class Observations
{
    /**
     * @var string
     */
    private $observationId = '';

    /**
     * @var bool
     */
    private $timeSerie = true;

    /**
     * @var ObservedProperty
     */
    private $observedProperty;

    /**
     * @var FeatureOfInterest
     */
    private $featureOfInterest;

    /**
     * @var TemporalExtent
     */
    private $temporalExtent;

    /**
     * @var Result
     */
    private $result;

    /**
     * @var string
     */
    private $processingLevel;

    /**
     * @var string
     */
    private $dataType;

    //-------------------------constructor-------------------
    public function __construct()
    {
        $this->result = new Result();
    }

    //---------------------getter et setter

    /**
     * @return string
     */
    public function getObservationId(): string
    {
        return $this->observationId;
    }

    /**
     * @param string $observationId
     */
    public function setObservationId(string $observationId): void
    {
        $this->observationId = $observationId;
    }

    /**
     * @return bool
     */
    public function isTimeSerie(): bool
    {
        return $this->timeSerie;
    }

    /**
     * @param bool $timeSerie
     */
    public function setTimeSerie(bool $timeSerie): void
    {
        $this->timeSerie = $timeSerie;
    }

    /**
     * @return string
     */
    public function getProcessingLevel(): string
    {
        return $this->processingLevel;
    }

    /**
     * @param string $processingLevel
     */
    public function setProcessingLevel(string $processingLevel): void
    {
        $this->processingLevel = $processingLevel;
    }

    /**
     * @return string
     */
    public function getDataType(): string
    {
        return $this->dataType;
    }

    /**
     * @param string $dataType
     */
    public function setDataType(string $dataType): void
    {
        $this->dataType = $dataType;
    }

    /**
     * @return ObservedProperty
     */
    public function getObservedProperty(): ObservedProperty
    {
        return $this->observedProperty;
    }

    /**
     * @param ObservedProperty $observedProperty
     */
    public function setObservedProperty(ObservedProperty $observedProperty): void
    {
        $this->observedProperty = $observedProperty;
    }

    /**
     * @return FeatureOfInterest
     */
    public function getFeatureOfInterest(): FeatureOfInterest
    {
        return $this->featureOfInterest;
    }

    /**
     * @param FeatureOfInterest $featureOfInterest
     */
    public function setFeatureOfInterest(FeatureOfInterest $featureOfInterest): void
    {
        $this->featureOfInterest = $featureOfInterest;
    }

    /**
     * @return TemporalExtent
     */
    public function getTemporalExtent(): TemporalExtent
    {
        return $this->temporalExtent;
    }

    /**
     * @param TemporalExtent $temporalExtends
     */
    public function setTemporalExtent(TemporalExtent $temporalExtends): void
    {
        $this->temporalExtent = $temporalExtends;
    }

    /**
     * @return Result | null
     */
    public function getResult(): ?Result
    {
        return $this->result;
    }

    /**
     * @param Result $result
     */
    public function setResult(Result $result): void
    {
        $this->result = $result;
    }
}
