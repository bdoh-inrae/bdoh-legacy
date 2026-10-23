<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity;

/**
 * Class ChroniqueConvertie.
 */
class ChroniqueConvertie extends ChroniqueContinue
{
    /**
     * @var \DateTime
     */
    protected $miseAJour;

    /**
     * @var Conversion|null
     */
    protected $conversion;

    /**
     * @return \DateTime
     */
    public function getMiseAJour()
    {
        return $this->miseAJour;
    }

    /**
     * @param \DateTime $miseAJour
     *
     * @return ChroniqueConvertie
     */
    public function setMiseAJour($miseAJour)
    {
        $this->miseAJour = $miseAJour;

        return $this;
    }

    /**
     * Get conversion.
     *
     * @return Conversion|null
     */
    public function getConversion()
    {
        return $this->conversion;
    }

    /**
     * Set conversion.
     *
     * @param Conversion|null $conversion
     */
    public function setConversion(?Conversion $conversion = null)
    {
        $this->conversion = $conversion;
        if ($conversion) {
            $conversion->setSortie($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isConvertie()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function dtype()
    {
        return 'convertie';
    }
}
