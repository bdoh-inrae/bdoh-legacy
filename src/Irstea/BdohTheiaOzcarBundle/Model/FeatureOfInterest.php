<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohTheiaOzcarBundle\Model;

class FeatureOfInterest
{
    /***
     * @var SamplingFeature
     */
    public $samplingFeature;

    //------------------------------ctor------------------

    public function __construct()
    {
        $this->samplingFeature = new SamplingFeature();
    }

    //----------------------getter et setter-----------------------------------

    /**
     * @return SamplingFeature
     */
    public function getSamplingFeature(): SamplingFeature
    {
        return $this->samplingFeature;
    }

    /**
     * @return object
     */
    public function getProperties()
    {
        $objectVide = new \stdClass();

        return $objectVide;
    }

    /**
     * @param SamplingFeature $samplingFeature
     */
    public function setSamplingFeature(SamplingFeature $samplingFeature)
    {
        $this->samplingFeature = $samplingFeature;
    }
}
