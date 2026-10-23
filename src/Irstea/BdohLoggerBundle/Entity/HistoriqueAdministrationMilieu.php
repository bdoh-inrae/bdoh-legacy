<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\Milieu;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationMilieu extends HistoriqueAdministration
{
    /**
     * @var Milieu
     */
    protected $milieu;

    /**
     * @return Milieu
     */
    public function getMilieu(): Milieu
    {
        return $this->milieu;
    }

    /**
     * @param Milieu $milieu
     *
     * @return HistoriqueAdministrationMilieu
     */
    public function setMilieu(?Milieu $milieu = null)
    {
        $this->milieu = $milieu;

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
            $translator->trans('historique.milieu') . $translator->trans('deux_points') . ' ' .
            $this->getMilieu()
        );
    }
}
