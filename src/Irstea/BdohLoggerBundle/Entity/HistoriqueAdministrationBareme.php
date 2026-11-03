<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationBareme.
 */
class HistoriqueAdministrationBareme extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Bareme
     */
    protected $bareme;

    /**
     * Set bareme.
     *
     * @param \Irstea\BdohDataBundle\Entity\Bareme $bareme
     *
     * @return HistoriqueAdministrationBareme
     */
    public function setBareme(?\Irstea\BdohDataBundle\Entity\Bareme $bareme = null)
    {
        $this->bareme = $bareme;

        return $this;
    }

    /**
     * Get bareme.
     *
     * @return \Irstea\BdohDataBundle\Entity\Bareme
     */
    public function getBareme()
    {
        return $this->bareme;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.bareme') . $translator->trans('deux_points') . ' ' .
            $this->getBareme()
        );
    }
}
