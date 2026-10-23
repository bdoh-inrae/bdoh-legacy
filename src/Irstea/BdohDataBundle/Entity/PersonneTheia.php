<?php

namespace Irstea\BdohDataBundle\Entity;

use Irstea\BdohSecurityBundle\Entity\Utilisateur;

class PersonneTheia
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $prenom;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var Partenaire
     */
    protected $partenaire;

    /**
     * @var Utilisateur|null
     */
    protected $utilisateur;

    //----------------------------------------------getter et setter------------------------------------------------

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
     * Set email.
     *
     * @param string $email
     */
    public function setEmail(string $email)
    {
        $this->email = $email;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set prenom.
     *
     * @param string $prenom
     */
    public function setPrenom($prenom)
    {
        $this->prenom = $prenom;
    }

    /**
     * Get prenom.
     *
     * @return string
     */
    public function getPrenom()
    {
        return $this->prenom;
    }

    /**
     * Set nom.
     *
     * @param string $nom
     */
    public function setNom($nom)
    {
        $this->nom = $nom;
    }

    /**
     * Get nom.
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set Partenaire.
     *
     * @param Partenaire $partenaire
     *
     * @return PersonneTheia
     */
    public function setPartenaire(?Partenaire $partenaire = null)
    {
        $this->partenaire = $partenaire;

        return $this;
    }

    /**
     * Get Partenaire.
     *
     * @return Partenaire
     */
    public function getPartenaire()
    {
        return $this->partenaire;
    }

    /**
     * Set Utilisateur.
     *
     * @param Utilisateur $utilisateur
     *
     * @return PersonneTheia
     */
    public function setUtilisateur(?Utilisateur $utilisateur = null)
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    /**
     * Get Utilisateur.
     *
     * @return Utilisateur
     */
    public function getUtilisateur()
    {
        return $this->utilisateur;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getNom() . ' ' . $this->getPrenom() ?: '';
    }
}
