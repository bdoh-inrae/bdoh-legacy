<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\PersonneTheia;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationPersonneTheia extends HistoriqueAdministration
{
    /**
     * @var PersonneTheia
     */
    protected $personneTheia;

    /**
     * @return PersonneTheia
     */
    public function getPersonneTheia()
    {
        return $this->personneTheia;
    }

    /**
     * @param PersonneTheia $personneTheia
     *
     * @return HistoriqueAdministrationPersonneTheia
     */
    public function setPersonneTheia(?PersonneTheia $personneTheia = null)
    {
        $this->personneTheia = $personneTheia;

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
            $translator->trans('historique.personneTheia') . $translator->trans('deux_points') . ' ' .
            $this->getPersonneTheia()
        );
    }
}
