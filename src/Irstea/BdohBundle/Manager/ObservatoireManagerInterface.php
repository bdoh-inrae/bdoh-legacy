<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Manager;

use Irstea\BdohDataBundle\Entity\Observatoire;

/**
 * This class allows to :
 *   => load the 'observatoire' context ;
 *   => manage the current 'observatoire' ;
 *   => manage the css file of an 'observatoire' ;.
 */
interface ObservatoireManagerInterface
{
    /**
     * Gets the current 'observatoire'.
     *
     * @return Observatoire
     */
    public function getCurrent();
}
