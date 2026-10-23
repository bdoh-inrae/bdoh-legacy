<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\TheiaCategories;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationTheiaCategories extends HistoriqueAdministration
{
    /**
     * @var TheiaCategories
     */
    protected $theiaCategories;

    /**
     * @return TheiaCategories
     */
    public function getTheiaCategories()
    {
        return $this->theiaCategories;
    }

    /**
     * @param TheiaCategories $theiaCategories
     *
     * @return HistoriqueAdministrationTheiaCategories
     */
    public function setTheiaCategories(TheiaCategories $theiaCategories)
    {
        $this->theiaCategories = $theiaCategories;

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
            $translator->trans('historique.theiaCategories') . $translator->trans('deux_points') . ' ' .
            $this->getTheiaCategories()
        );
    }
}
