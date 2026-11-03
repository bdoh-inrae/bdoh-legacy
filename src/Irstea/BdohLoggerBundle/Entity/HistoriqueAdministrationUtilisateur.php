<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationUtilisateur.
 */
class HistoriqueAdministrationUtilisateur extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohSecurityBundle\Entity\Utilisateur
     */
    protected $utilisateur;

    /**
     * Set utilisateur.
     *
     * @param \Irstea\BdohSecurityBundle\Entity\Utilisateur $utilisateur
     *
     * @return HistoriqueAdministrationUtilisateur
     */
    public function setUtilisateur(?\Irstea\BdohSecurityBundle\Entity\Utilisateur $utilisateur = null)
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    /**
     * Get utilisateur.
     *
     * @return \Irstea\BdohSecurityBundle\Entity\Utilisateur
     */
    public function getUtilisateur()
    {
        return $this->utilisateur;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.utilisateur') . $translator->trans('deux_points') . ' ' .
            $this->getUtilisateur()
        );
    }
}
