<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * JeuConversion.
 */
class JeuConversion
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var Conversion
     */
    private $conversion;

    /**
     * @var \DateTime
     */
    private $dateCreation;

    /**
     * @var int
     */
    private $paramConversion;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set conversion.
     *
     * @param Conversion $conversion
     *
     * @return JeuConversion
     */
    public function setConversion(?Conversion $conversion = null)
    {
        $this->conversion = $conversion;

        return $this;
    }

    /**
     * Get conversion.
     *
     * @return Conversion
     */
    public function getConversion()
    {
        return $this->conversion;
    }

    /**
     * Set dateCreation.
     *
     * @param \DateTime $dateCreation
     *
     * @return JeuConversion
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation.
     *
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * equals.
     *
     * @param JeuConversion $jeuConversion
     *
     * @return bool
     */
    public function equals($jeuConversion)
    {
        return $jeuConversion && ($this->getParamConversion() === $jeuConversion->getParamConversion());
    }

    /**
     * Set paramConversion.
     *
     * @param int $paramConversion
     *
     * @return JeuConversion
     */
    public function setParamConversion($paramConversion)
    {
        $this->paramConversion = $paramConversion;

        return $this;
    }

    /**
     * Get paramConversion.
     *
     * @return int
     */
    public function getParamConversion()
    {
        return $this->paramConversion;
    }
}
