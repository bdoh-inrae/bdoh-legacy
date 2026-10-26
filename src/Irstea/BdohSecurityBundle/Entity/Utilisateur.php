<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\SiteExperimental;
use Irstea\BdohSecurityBundle\Validator\Constraint\EmailWithMx;
use Serializable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: 'email', message: 'Utilisateur.email.alreadyUsed')]
class Utilisateur implements UserInterface, EquatableInterface, Serializable
{
    const ROLE_ADMIN_FCT = 'FCT';
    const ROLE_ADMIN_TECH = 'TECH';

    // Dans confirmationToken, un compte inscrit par le formulaire dont l'adresse attend d'être
    // confirmée par une première connexion (BdohAuthenticationHandler)
    const AWAITING_CONFIRMATION = 'inscription';

    /***************************************************************************
     * Data stored in database
     **************************************************************************/

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    #[EmailWithMx]
    protected $email;

    /**
     * @var string
     *             Today : not used !
     */
    protected $login;

    /**
     * @var string
     */
    #[Assert\NotBlank]
    protected $prenom;

    /**
     * @var string
     */
    #[Assert\NotBlank]
    protected $nom;

    /**
     * @var string
     *             Plus de mot de passe depuis la connexion par lien : '' pour les nouveaux comptes, la colonne
     *             n'acceptant pas NULL ; les hachages des anciens comptes ne servent plus.
     */
    protected $password;

    /**
     * @var string salt
     */
    protected $salt;

    /**
     * @var string
     *             Assert\NotNull()
     */
    protected $organisme;

    /**
     * @var string
     */
    #[Assert\Length(min: 10, minMessage: 'Utilisateur.telephone.minLength({{ limit }})')]
    protected $telephone;

    /**
     * @var string
     *             Assert\NotNull()
     */
    protected $adresse;

    /**
     * @var \dateTime
     */
    protected $finAcces;

    /**
     * @var \DateTime
     */
    protected $derniereConnection;

    /**
     * @var Categorie
     */
    protected $categorie;

    /**
     * @var Besoin[]
     */
    protected $besoins;

    /**
     * @var RoleObservatoire[]
     */
    protected $rolesObservatoire;

    /**
     * @var RoleSite[]
     */
    protected $rolesSite;

    /**
     * @var RoleChronique[]
     */
    protected $rolesChronique;

    /**
     * @var string
     */
    protected $confirmationToken;

    /**
     * @var string|null
     */
    protected $roleAdmin;

    /**
     * Utilisateur constructor.
     *
     * @param int $id
     */
    public function __construct($id = null)
    {
        $this->id = $id;
        $this->besoins = new ArrayCollection();
        $this->rolesObservatoire = new ArrayCollection();
        $this->rolesSite = new ArrayCollection();
        $this->rolesChronique = new ArrayCollection();
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
     * Set email.
     *
     * @param string $email
     */
    public function setEmail($email)
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
     * Set login.
     *
     * @param string $login
     */
    public function setLogin($login)
    {
        $this->login = $login;
    }

    /**
     * Get login.
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * Set password.
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Get password.
     *
     * @return string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set salt.
     *
     * @param string $salt
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;
    }

    /**
     * Gets salt.
     * This is generated if it doesn't exist.
     *
     * @return string
     */
    public function getSalt(): ?string
    {
        if (null === $this->salt) {
            $this->salt = bin2hex(random_bytes(16));
        }

        return $this->salt;
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
     * Set organisme.
     *
     * @param string $organisme
     */
    public function setOrganisme($organisme)
    {
        $this->organisme = $organisme;
    }

    /**
     * Get organisme.
     *
     * @return string
     */
    public function getOrganisme()
    {
        return $this->organisme;
    }

    /**
     * Set telephone.
     *
     * @param string $telephone
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;
    }

    /**
     * Get telephone.
     *
     * @return string
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * Set adresse.
     *
     * @param string $adresse
     */
    public function setAdresse($adresse)
    {
        $this->adresse = $adresse;
    }

    /**
     * Get adresse.
     *
     * @return string
     */
    public function getAdresse()
    {
        return $this->adresse;
    }

    /**
     * Set finAcces.
     *
     * @param \dateTime $finAcces
     */
    public function setFinAcces($finAcces)
    {
        $this->finAcces = $finAcces;
    }

    /**
     * Get finAcces.
     *
     * @return \DateTime
     */
    public function getFinAcces()
    {
        return $this->finAcces;
    }

    /**
     * Set derniereConnection.
     *
     * @param \DateTime $derniereConnection
     */
    public function setDerniereConnection(\DateTime $derniereConnection)
    {
        $this->derniereConnection = $derniereConnection;
    }

    /**
     * Get derniereConnection.
     *
     * @return string
     */
    public function getDerniereConnection()
    {
        return $this->derniereConnection;
    }

    /**
     * Set categorie.
     *
     * @param Categorie $categorie
     */
    public function setCategorie(Categorie $categorie)
    {
        $this->categorie = $categorie;
    }

    /**
     * Get categorie.
     *
     * @return Categorie
     */
    public function getCategorie()
    {
        return $this->categorie;
    }

    /**
     * Add besoins.
     *
     * @param Besoin $besoins
     */
    public function addBesoin(Besoin $besoins)
    {
        $this->besoins[] = $besoins;
    }

    /**
     * Get besoins.
     *
     * @return Besoin[]|Collection
     */
    public function getBesoins()
    {
        return $this->besoins;
    }

    /**
     * Add rolesObservatoire.
     *
     * @param RoleObservatoire $rolesObservatoire
     */
    public function addRoleObservatoire(RoleObservatoire $rolesObservatoire)
    {
        $this->rolesObservatoire[] = $rolesObservatoire;
    }

    /**
     * Remove rolesObservatoire.
     *
     * @param RoleObservatoire $rolesObservatoire
     */
    public function removeRoleObservatoire(RoleObservatoire $rolesObservatoire)
    {
        $this->rolesObservatoire->removeElement($rolesObservatoire);
    }

    /**
     * Get rolesObservatoire.
     *
     * Secured with isAccountExpired.
     *
     * @return RoleObservatoire[]|Collection
     */
    public function getRolesObservatoire()
    {
        if ($this->isAccountExpired()) {
            return [];
        }

        return $this->rolesObservatoire;
    }

    /**
     * Add rolesSite.
     *
     * @param RoleSite $rolesSite
     */
    public function addRoleSite(RoleSite $rolesSite)
    {
        $this->rolesSite[] = $rolesSite;
    }

    /**
     * Remove rolesSite.
     *
     * @param RoleSite $rolesSite
     */
    public function removeRoleSite(RoleSite $rolesSite)
    {
        $this->rolesSite->removeElement($rolesSite);
    }

    /**
     * Get rolesSite.
     *
     * Secured with isAccountExpired.
     *
     * @return RoleSite[]|Collection
     */
    public function getRolesSite()
    {
        if ($this->isAccountExpired()) {
            return [];
        }

        return $this->rolesSite;
    }

    /**
     * Add rolesChronique.
     *
     * @param RoleChronique $rolesChronique
     */
    public function addRoleChronique(RoleChronique $rolesChronique)
    {
        $this->rolesChronique[] = $rolesChronique;
    }

    /**
     * Remove rolesChronique.
     *
     * @param RoleChronique $rolesChronique
     */
    public function removeRoleChronique(RoleChronique $rolesChronique)
    {
        $this->rolesChronique->removeElement($rolesChronique);
    }

    /**
     * Get rolesChronique.
     *
     * Secured with isAccountExpired.
     *
     * @return RoleChronique[]|Collection
     */
    public function getRolesChronique()
    {
        if ($this->isAccountExpired()) {
            return [];
        }

        return $this->rolesChronique;
    }

    /**
     * Set confirmationToken.
     *
     * @param string $confirmationToken
     */
    public function setConfirmationToken($confirmationToken)
    {
        $this->confirmationToken = $confirmationToken;
    }

    /**
     * Get confirmationToken.
     *
     * @return string
     */
    public function getConfirmationToken()
    {
        return $this->confirmationToken;
    }

    /***************************************************************************
     * Methods used for User authorization
     **************************************************************************/

    /**
     * Factorization function for very similar behaviour in the three "hasRoleYYY" functions.
     *
     * @param string[]|string $expectedRoles
     * @param mixed[]|mixed   $targets
     * @param Role[]          $actualRoles
     *
     * @return bool
     */
    private function hasRole($expectedRoles, $targets, $actualRoles)
    {
        if (!\is_array($expectedRoles) && !($expectedRoles instanceof \Traversable)) {
            $expectedRoles = [$expectedRoles];
        }

        if (!\is_array($targets) && !($targets instanceof \Traversable)) {
            $targets = [$targets];
        }

        foreach ($expectedRoles as $role) {
            foreach ($targets as $target) {
                foreach ($actualRoles as $actualRole) {
                    if ($actualRole->matches($this, $role, $target)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Determines if $this has some Roles regarding any of Chroniques.
     *
     * @param string[]|string       $roles
     * @param Chronique[]|Chronique $chroniques
     *
     * @return bool
     */
    public function hasRoleChronique($roles, $chroniques)
    {
        return $this->hasRole($roles, $chroniques, $this->rolesChronique);
    }

    /**
     * Determines if $this has some Roles regarding any of Chroniques.
     *
     * Secured with isAccountExpired.
     *
     * @param string[]|string       $roles
     * @param Chronique[]|Chronique $chroniques
     *
     * @return bool
     */
    public function securedHasRoleChronique($roles, $chroniques)
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        return $this->hasRole($roles, $chroniques, $this->rolesChronique);
    }

    /**
     * Determines if $this has some Roles regarding any of Sites.
     *
     * @param string[]|string                     $roles
     * @param SiteExperimental[]|SiteExperimental $sites
     *
     * @return bool
     */
    public function hasRoleSite($roles, $sites)
    {
        return $this->hasRole($roles, $sites, $this->rolesSite);
    }

    /**
     * Determines if $this has some Roles regarding any of Sites.
     *
     * Secured with isAccountExpired.
     *
     * @param string[]|string                     $roles
     * @param SiteExperimental[]|SiteExperimental $sites
     *
     * @return bool
     */
    public function securedHasRoleSite($roles, $sites)
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        return $this->hasRole($roles, $sites, $this->rolesSite);
    }

    /**
     * Determines if $this has some Roles regarding any of Observatoires.
     *
     * @param string[]|string             $roles
     * @param Observatoire[]|Observatoire $observatoires
     *
     * @return bool
     */
    public function hasRoleObservatoire($roles, $observatoires)
    {
        return $this->hasRole($roles, $observatoires, $this->rolesObservatoire);
    }

    /**
     * Determines if $this has some Roles regarding any of Observatoires.
     *
     * Secured with isAccountExpired.
     *
     * @param string[]|string             $roles
     * @param Observatoire[]|Observatoire $observatoires
     *
     * @return bool
     */
    public function securedHasRoleObservatoire($roles, $observatoires)
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        return $this->hasRole($roles, $observatoires, $this->rolesObservatoire);
    }

    /**
     * Part of UserInterface.
     * Returns the roles granted to the user.
     *
     * Secured with isAccountExpired.
     *
     * @return array
     */
    public function getRoles(): array
    {
        // This is supposed to be the minimum for functionnal Users
        $roles = ['ROLE_USER'];

        if ($this->isAccountExpired()) {
            return $roles;
        }

        $obs = Observatoire::getCurrent();
        if ($obs) {
            $sites = $obs->getSites();

            if ($this->hasRoleObservatoire(Role::GESTIONNAIRE, $obs)) {
                $roles[] = 'ROLE_ADMIN';
            } elseif ($this->hasRoleSite([Role::GESTIONNAIRE, Role::CONTRIBUTEUR], $sites)) {
                $roles[] = 'ROLE_ADMIN';
            }
        }

        if ($this->roleAdmin !== null) {
            $roles[] = 'ROLE_ADMIN_' . $this->roleAdmin;
        }

        return $roles;
    }

    /**
     * Get roleAdmin.
     *
     * @return string|null
     */
    public function getRoleAdmin()
    {
        return $this->roleAdmin;
    }

    /**
     * Set roleAdmin.
     *
     * @param string $roleAdmin
     *
     * @return self
     */
    public function setRoleAdmin($roleAdmin)
    {
        if (!in_array($roleAdmin, [null, self::ROLE_ADMIN_TECH, self::ROLE_ADMIN_FCT], true)) {
            throw new \InvalidArgumentException("Invalid roleAdmin: $roleAdmin");
        }
        $this->roleAdmin = $roleAdmin;

        return $this;
    }

    /**
     * isGestionnaireDesObservatoires.
     *
     * Secured with isAccountExpired.
     *
     * @return bool
     */
    public function isGestionnaireDesObservatoires()
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        return $this->getRoleAdmin() !== null;
    }

    /**
     * @param bool $enable
     */
    public function setGestionnaireDesObservatoires($enable)
    {
        if ($enable && $this->roleAdmin === null) {
            $this->roleAdmin = self::ROLE_ADMIN_FCT;
        } elseif (!$enable && $this->roleAdmin === self::ROLE_ADMIN_FCT) {
            $this->roleAdmin = null;
        }
    }

    /**
     * isATrustedUserOfCurrentObservatory.
     *
     * Secured with isAccountExpired.
     *
     * @return bool
     */
    public function isATrustedUserOfCurrentObservatory()
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        $obs = Observatoire::getCurrent();
        if ($obs) {
            return $this->hasRoleObservatoire([Role::UTILISATEUR], $obs);
        }

        return false;
    }

    /**
     * isAContributorOfCurrentObservatory.
     *
     * Secured with isAccountExpired.
     *
     * @return bool
     */
    public function isAContributorOfCurrentObservatory()
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        $obs = Observatoire::getCurrent();
        if ($obs) {
            $sites = $obs->getSites();

            return $this->hasRoleSite([Role::CONTRIBUTEUR], $sites);
        }

        return false;
    }

    /**
     * isASiteManagerOfCurrentObservatory.
     *
     * Secured with isAccountExpired.
     *
     * @return bool
     */
    public function isASiteManagerOfCurrentObservatory()
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        $obs = Observatoire::getCurrent();
        if ($obs) {
            $sites = $obs->getSites();

            return $this->hasRoleSite([Role::GESTIONNAIRE], $sites);
        }

        return false;
    }

    /**
     * isAManagerOfCurrentObservatory.
     *
     * Secured with isAccountExpired.
     *
     * @return bool
     */
    public function isAManagerOfCurrentObservatory()
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        $obs = Observatoire::getCurrent();
        if ($obs) {
            return $this->hasRoleObservatoire([Role::GESTIONNAIRE], $obs);
        }

        return false;
    }

    /***************************************************************************
     * Methods used for User authentication
     **************************************************************************/

    /**
     * Part of UserInterface.
     * Returns the username used to authenticate the user.
     */
    public function getUsername()
    {
        return $this->getEmail();
    }

    /**
     * Part of UserInterface, since Symfony 5.3.
     */
    public function getUserIdentifier(): string
    {
        return $this->getEmail();
    }

    /**
     * Part of UserInterface.
     * Determines if two users are equals.
     *
     * @param UserInterface $user
     *
     * @return bool
     */
    public function equals(UserInterface $user)
    {
        return $this->isEqualTo($user);
    }

    /**
     * Part of EquatableInterface: the user stays logged in while the one in session has the same id
     * and e-mail, as with equals() in Symfony 2.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (null !== $user) {
            return ($this->getId() === $user->getId()) &&
                ($this->getUsername() === $user->getUsername());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonExpired()
    {
        return true; //(!$this->finAcces) || ($this->finAcces > new \DateTime('now'));
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonLocked()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isCredentialsNonExpired()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountExpired()
    {
        return $this->finAcces !== null && $this->finAcces < new \DateTime('now');
    }

    /***************************************************************************
     * Other methods.
     **************************************************************************/

    /**
     * Part of UserInterface.
     * Removes sensitive data from the user.
     */
    public function eraseCredentials(): void
    {
        /*
         * TODO: track the following BUG.
         * If we erase 'password' and 'salt' fields,
         * SonataAdminBundle detects that 'Utilisateur' has changed.
         * Then, he tries to update it (why ?? I don't know) and PostegrSQL crashes the error
         * "[...] Not null violation: 7 ERREUR: une valeur NULL viole la contrainte NOT NULL de la colonne "password" [...]".
         * Prehaps, the next updates of SonataAdminBundle will resolve this bug.
         */

        //$this->password = null;
        //$this->salt = null;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(
            [
                'id'    => $this->getId(),
                'email' => $this->getEmail(),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serializedData)
    {
        $this->__unserialize(unserialize($serializedData));
    }

    public function __serialize(): array
    {
        return [
            'id'    => $this->getId(),
            'email' => $this->getEmail(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->prenom . ' ' . $this->nom;
    }
}
