<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\TypeFunding;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationTypeFunding extends HistoriqueAdministration
{
    /**
     * @var TypeFunding
     */
    protected $typefunding;

    /**
     * @return TypeFunding|null
     */
    public function getTypefunding()
    {
        return $this->typefunding;
    }

    /**
     * @param TypeFunding $typefunding
     *
     * @return HistoriqueAdministrationTypeFunding
     */
    public function setTypefunding(?TypeFunding $typefunding = null)
    {
        $this->typefunding = $typefunding;

        return $this;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.typefunding') . $translator->trans('deux_points') . ' ' .
            $this->getTypefunding()
        );
    }
}
