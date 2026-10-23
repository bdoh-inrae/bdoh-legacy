<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Entity;

/**
 * Class Role.
 */
abstract class Role
{
    const GESTIONNAIRE = 'gestionnaire';
    const CONTRIBUTEUR = 'contributeur';
    const UTILISATEUR = 'utilisateur';

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $valeur;

    /**
     * @var Utilisateur
     */
    protected $utilisateur;

    /**
     * Role constructor.
     *
     * @param Utilisateur|null $utilisateur
     * @param string|null      $valeur
     * @param mixed|null       $target
     */
    public function __construct(?Utilisateur $utilisateur = null, $valeur = null, $target = null)
    {
        if ($utilisateur !== null) {
            $this->setUtilisateur($utilisateur);
        }
        if ($valeur !== null) {
            $this->setValeur($valeur);
        }
        if ($target !== null) {
            $this->setTarget($target);
        }
    }

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
     * Set utilisateur.
     *
     * @param Utilisateur $utilisateur
     */
    public function setUtilisateur(Utilisateur $utilisateur)
    {
        $this->utilisateur = $utilisateur;
    }

    /**
     * Get utilisateur.
     *
     * @return Utilisateur
     */
    public function getUtilisateur()
    {
        return $this->utilisateur;
    }

    /**
     * Set valeur.
     *
     * @param string $valeur
     */
    public function setValeur($valeur)
    {
        $this->valeur = $valeur;
    }

    /**
     * Get valeur.
     *
     * @return string
     */
    public function getValeur()
    {
        return $this->valeur;
    }

    /**
     * @param mixed  $user
     * @param string $type
     * @param mixed  $target
     *
     * @return bool
     */
    public function matches($user, $type, $target)
    {
        return ($user instanceof Utilisateur && $user->getId() === $this->utilisateur->getId()) &&
            $type === $this->valeur &&
            $this->isTarget($target);
    }

    /**
     * @return bool
     */
    protected function isValid()
    {
        return $this->getTarget() !== null &&
            $this->utilisateur instanceof Utilisateur &&
            in_array($this->valeur, [self::CONTRIBUTEUR, self::GESTIONNAIRE, self::UTILISATEUR], true);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->valeur . '@' . (string) $this->getTarget();
    }

    /**
     * @param mixed $target
     *
     * @return bool
     */
    abstract protected function isTarget($target);

    /**
     * @return mixed
     */
    abstract public function getTarget();

    /**
     * @param mixed $target
     */
    abstract public function setTarget($target);
}
