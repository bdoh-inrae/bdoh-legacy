<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\InspireTheme;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationInspireTheme extends HistoriqueAdministration
{
    /**
     * @var InspireTheme
     */
    protected $inspireTheme;

    /**
     * @return InspireTheme
     */
    public function getInspireTheme(): InspireTheme
    {
        return $this->inspireTheme;
    }

    /**
     * @param InspireTheme $inspireTheme
     *
     * @return HistoriqueAdministrationInspireTheme
     */
    public function setInspireTheme(InspireTheme $inspireTheme)
    {
        $this->inspireTheme = $inspireTheme;

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
            $translator->trans('historique.inspireTheme') . $translator->trans('deux_points') . ' ' .
            $this->getInspireTheme()
        );
    }
}
